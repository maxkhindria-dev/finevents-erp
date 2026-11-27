<?php

/*
| Safety nets: tests that fail if someone adds code that skips the
| permission system, rather than testing one rule.
*/

use App\Access\AccessContext;
use App\Access\HasVisibilityRules;
use App\Access\ScopedToViewer;
use App\Audit\Audited;
use App\Enums\Role;
use App\Models\AuditEvent;
use App\Models\Entity;
use App\Models\Person;
use App\Models\Project;
use Illuminate\Database\Eloquent\MassAssignmentException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;

/** @return list<class-string> */
function appModelClasses(): array
{
    return collect(glob(app_path('Models/*.php')))
        ->map(fn (string $file) => 'App\\Models\\'.basename($file, '.php'))
        ->values()->all();
}

it('makes every model use the automatic record filter', function () {
    foreach (appModelClasses() as $class) {
        expect(in_array(ScopedToViewer::class, class_uses_recursive($class), true))
            ->toBeTrue("{$class} must use ScopedToViewer (deny by default)")
            ->and(is_subclass_of($class, HasVisibilityRules::class))
            ->toBeTrue("{$class} must implement HasVisibilityRules");
    }
});

it('audits every model except the audit log itself', function () {
    foreach (appModelClasses() as $class) {
        if ($class === AuditEvent::class) {
            continue;
        }

        expect(in_array(Audited::class, class_uses_recursive($class), true))
            ->toBeTrue("{$class} must use Audited so its changes are logged");
    }
});

it('gives every model a policy rule book', function () {
    foreach (appModelClasses() as $class) {
        expect(Gate::getPolicyFor($class))->not->toBeNull("{$class} needs a policy in app/Policies");
    }
});

it('puts every route in one of the three tiers: public, second-factor pending, or fully checked', function () {
    // 1. Public: sign-in page, Google round trip, health check.
    $public = ['/', 'up', 'auth/google', 'auth/google/callback'];
    // 2. Signed in but before the second factor: only MFA screens and sign-out.
    $preMfa = ['mfa', 'mfa/setup', 'sign-out'];
    $checked = 0;

    foreach (Route::getRoutes() as $route) {
        $uri = $route->uri();
        $middleware = $route->gatherMiddleware();

        if (in_array($uri, $public, true)) {
            continue;
        }

        $checked++;
        expect(in_array('auth', $middleware, true))->toBeTrue("Route {$uri} must require sign-in");

        if (in_array($uri, $preMfa, true)) {
            continue;
        }

        expect(in_array('mfa', $middleware, true))->toBeTrue("Route {$uri} must require the second factor")
            ->and(collect($middleware)->contains(fn ($m) => str_starts_with((string) $m, 'can:')))
            ->toBeTrue("Route {$uri} must have a can: permission check");
    }

    // Every route was either checked above or is on the short public list.
    expect($checked + count($public))->toBe(count(Route::getRoutes()->getRoutes()));
});

it('does not expose Laravel\'s file serving or upload routes', function () {
    $uris = collect(Route::getRoutes())->map->uri()->all();

    expect(collect($uris)->contains(fn ($uri) => str_starts_with($uri, 'storage')))->toBeFalse();
});

it('does not let role or status be set by mass assignment', function (string $field, string $value) {
    expect(fn () => (new Person)->fill([$field => $value]))->toThrow(MassAssignmentException::class);
})->with([
    'role' => ['role', 'owner'],
    'status' => ['status', 'active'],
]);

it('allows only one Owner account, enforced by the database itself', function () {
    $entity = app(AccessContext::class)->runAsSystem(fn () => Entity::factory()->code('FR')->create());
    Person::factory()->owner()->in($entity)->create();

    expect(fn () => Person::factory()->role(Role::Owner)->in($entity)->create())
        ->toThrow(QueryException::class);
});

it('records who created a record', function () {
    $entity = app(AccessContext::class)->runAsSystem(fn () => Entity::factory()->code('FR')->create());
    $admin = Person::factory()->admin()->in($entity)->create();

    $project = app(AccessContext::class)->runAs($admin, fn () => Project::factory()->in($entity)->create());

    expect($project->created_by)->toBe($admin->id);
});
