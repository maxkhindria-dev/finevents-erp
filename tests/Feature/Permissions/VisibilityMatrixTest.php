<?php

/*
| THE PERMISSION MATRIX (CLAUDE.md §5.2: "automated tests prove each role
| cannot reach the data of a higher role or another entity").
|
| For every person in the test company (tests/Support/AccessWorld.php) we list
| exactly what they should see. Each expectation is checked against BOTH
| independent layers:
|   - layer 1, the policy rule books (what a page or action would allow), and
|   - layer 2, the automatic record filter (what a database query returns).
| If either layer shows one record too many or too few, the build fails.
*/

use App\Access\AccessContext;
use App\Models\Client;
use App\Models\Document;
use App\Models\Person;
use App\Models\Project;
use App\Models\ProjectFinancial;
use Tests\Support\AccessWorld;

beforeEach(function () {
    $this->world = AccessWorld::build();
});

/**
 * @param  list<string>  $expected
 */
function assertBothLayers(AccessWorld $world, ?string $viewerName, string $modelClass, array $named, array $expected): void
{
    $viewer = $viewerName === null ? null : $world->person($viewerName);
    sort($expected);

    expect($world->namesVisibleByQuery($viewer, $modelClass, $named))
        ->toBe($expected, 'Layer 2 (record filter) disagrees for '.($viewerName ?? 'nobody'));

    expect($world->namesAllowedByPolicy($viewer, $named))
        ->toBe($expected, 'Layer 1 (policy) disagrees for '.($viewerName ?? 'nobody'));
}

it('shows each person exactly the right projects', function (?string $viewer, array $expected) {
    assertBothLayers($this->world, $viewer, Project::class, $this->world->projects, $expected);
})->with([
    'owner sees every project' => ['owner', ['A_FR', 'X_FR', 'A_UK', 'X_UK', 'A_US', 'X_US']],
    'super admin sees every project' => ['super_admin', ['A_FR', 'X_FR', 'A_UK', 'X_UK', 'A_US', 'X_US']],
    'France admin sees France only' => ['admin_fr', ['A_FR', 'X_FR']],
    'UK admin sees UK and the extra US entity' => ['admin_uk', ['A_UK', 'X_UK', 'A_US', 'X_US']],
    'user sees only assigned project' => ['user_fr', ['A_FR']],
    'freelancer sees only assigned project' => ['freelancer_fr', ['A_FR']],
    'expired freelancer sees nothing' => ['freelancer_expired', []],
    'suspended user sees nothing' => ['user_suspended', []],
    'unassigned user sees nothing' => ['user_uk', []],
    'nobody signed in sees nothing' => [null, []],
]);

it('shows each person exactly the right clients', function (?string $viewer, array $expected) {
    assertBothLayers($this->world, $viewer, Client::class, $this->world->clients, $expected);
})->with([
    'owner sees every client' => ['owner', ['CA_FR', 'CX_FR', 'CA_UK', 'CX_UK', 'CA_US', 'CX_US']],
    'super admin sees every client' => ['super_admin', ['CA_FR', 'CX_FR', 'CA_UK', 'CX_UK', 'CA_US', 'CX_US']],
    'France admin sees France clients' => ['admin_fr', ['CA_FR', 'CX_FR']],
    'UK admin sees UK and US clients' => ['admin_uk', ['CA_UK', 'CX_UK', 'CA_US', 'CX_US']],
    'user sees project client and directly assigned client' => ['user_fr', ['CA_FR', 'CX_FR']],
    'freelancer sees only their project client' => ['freelancer_fr', ['CA_FR']],
    'expired freelancer sees nothing' => ['freelancer_expired', []],
    'suspended user sees nothing' => ['user_suspended', []],
    'unassigned user sees nothing' => ['user_uk', []],
    'nobody signed in sees nothing' => [null, []],
]);

it('shows each person exactly the right people', function (?string $viewer, array $expected) {
    assertBothLayers($this->world, $viewer, Person::class, $this->world->people, $expected);
})->with([
    'owner sees everyone' => ['owner', ['owner', 'super_admin', 'admin_fr', 'admin_uk', 'user_fr', 'freelancer_fr', 'freelancer_expired', 'user_suspended', 'user_uk']],
    'super admin sees everyone' => ['super_admin', ['owner', 'super_admin', 'admin_fr', 'admin_uk', 'user_fr', 'freelancer_fr', 'freelancer_expired', 'user_suspended', 'user_uk']],
    'France admin sees people based in France' => ['admin_fr', ['owner', 'admin_fr', 'user_fr', 'freelancer_fr', 'freelancer_expired', 'user_suspended']],
    'UK admin sees people based in UK and US' => ['admin_uk', ['super_admin', 'admin_uk', 'user_uk']],
    'user sees only themselves' => ['user_fr', ['user_fr']],
    'freelancer sees only themselves' => ['freelancer_fr', ['freelancer_fr']],
    'expired freelancer sees nobody' => ['freelancer_expired', []],
    'suspended user sees nobody' => ['user_suspended', []],
    'nobody signed in sees nobody' => [null, []],
]);

it('shows project budgets only with the deal-values permission', function (?string $viewer, array $expected) {
    assertBothLayers($this->world, $viewer, ProjectFinancial::class, $this->world->financials, $expected);
})->with([
    'owner sees every budget' => ['owner', ['A_FR', 'X_FR', 'A_UK', 'X_UK', 'A_US', 'X_US']],
    'super admin sees every budget' => ['super_admin', ['A_FR', 'X_FR', 'A_UK', 'X_UK', 'A_US', 'X_US']],
    'France admin with France grant sees France budgets' => ['admin_fr', ['A_FR', 'X_FR']],
    'UK admin whose grant expired sees no budgets' => ['admin_uk', []],
    'user with UK grant but no UK projects sees no budgets' => ['user_fr', []],
    'freelancer sees no budgets' => ['freelancer_fr', []],
    'unassigned user sees no budgets' => ['user_uk', []],
    'nobody signed in sees no budgets' => [null, []],
]);

it('shows documents by project or client access, and freelancers only freelancer-allowed ones', function (?string $viewer, array $expected) {
    assertBothLayers($this->world, $viewer, Document::class, $this->world->documents, $expected);
})->with([
    'owner sees every document' => ['owner', ['D_A_FR_internal', 'D_A_FR_free', 'D_X_UK_internal', 'D_CX_FR_internal', 'D_CA_FR_free']],
    'France admin sees France documents' => ['admin_fr', ['D_A_FR_internal', 'D_A_FR_free', 'D_CX_FR_internal', 'D_CA_FR_free']],
    'UK admin sees UK documents' => ['admin_uk', ['D_X_UK_internal']],
    'user sees documents of their project and clients' => ['user_fr', ['D_A_FR_internal', 'D_A_FR_free', 'D_CX_FR_internal', 'D_CA_FR_free']],
    'freelancer sees only freelancer-allowed documents' => ['freelancer_fr', ['D_A_FR_free', 'D_CA_FR_free']],
    'expired freelancer sees nothing' => ['freelancer_expired', []],
    'unassigned user sees nothing' => ['user_uk', []],
    'nobody signed in sees nothing' => [null, []],
]);

it('lets the system (seeders, scheduled jobs) see everything, and only when asked explicitly', function () {
    $context = app(AccessContext::class);

    expect(Project::query()->count())->toBe(0)
        ->and($context->runAsSystem(fn () => Project::query()->count()))->toBe(6)
        ->and(Project::query()->count())->toBe(0);
});

it('restores the previous access after a failure inside system mode', function () {
    $context = app(AccessContext::class);

    try {
        $context->runAsSystem(fn () => throw new RuntimeException('boom'));
    } catch (RuntimeException) {
    }

    expect($context->isSystem())->toBeFalse()
        ->and(Project::query()->count())->toBe(0);
});

it('hides records a person cannot see even when they ask for one by its ID', function () {
    $userFr = $this->world->person('user_fr');
    $hiddenId = $this->world->projects['X_UK']->id;

    $found = app(AccessContext::class)->runAs($userFr, fn () => Project::query()->find($hiddenId));

    expect($found)->toBeNull();
});

it('applies the filter to the signed-in person automatically', function () {
    $this->actingAs($this->world->person('admin_fr'));

    expect(Project::query()->pluck('name')->sort()->values()->all())->toBe(['A_FR', 'X_FR']);
});
