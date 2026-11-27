<?php

/*
| Who may DO what (layer 1 policies), beyond viewing.
| Each case reads: [acting person, target, expected answer].
*/

use App\Access\AccessContext;
use App\Enums\Role;
use App\Enums\SensitiveField;
use App\Models\Client;
use App\Models\Person;
use App\Models\Project;
use App\Models\SensitiveGrant;
use Illuminate\Support\Facades\Gate;
use Tests\Support\AccessWorld;

beforeEach(function () {
    $this->world = AccessWorld::build();
});

function can(AccessWorld $world, string $actor, string $ability, mixed $arguments): bool
{
    return Gate::forUser($world->person($actor))->allows($ability, $arguments);
}

// --- Role changes -----------------------------------------------------------

it('controls who can change whose role, and to what', function (string $actor, string $target, Role $newRole, bool $expected) {
    $target = $this->world->person($target);

    expect(can($this->world, $actor, 'changeRole', [$target, $newRole]))->toBe($expected);
})->with([
    'owner can promote a user to super admin' => ['owner', 'user_fr', Role::SuperAdmin, true],
    'owner can demote a super admin' => ['owner', 'super_admin', Role::Admin, true],
    'nobody can make a second owner (owner)' => ['owner', 'super_admin', Role::Owner, false],
    'nobody can make a second owner (super admin)' => ['super_admin', 'admin_fr', Role::Owner, false],
    'owner cannot change own role' => ['owner', 'owner', Role::User, false],
    'super admin can promote a user to admin' => ['super_admin', 'user_fr', Role::Admin, true],
    'super admin can demote an admin to user' => ['super_admin', 'admin_fr', Role::User, true],
    'super admin cannot create a super admin' => ['super_admin', 'admin_fr', Role::SuperAdmin, false],
    'super admin cannot change the owner' => ['super_admin', 'owner', Role::User, false],
    'super admin cannot change own role' => ['super_admin', 'super_admin', Role::Admin, false],
    'admin cannot promote anyone to super admin' => ['admin_fr', 'user_fr', Role::SuperAdmin, false],
    'admin cannot promote a user to admin' => ['admin_fr', 'user_fr', Role::Admin, false],
    'user cannot change roles' => ['user_fr', 'freelancer_fr', Role::Admin, false],
    'user cannot promote themselves' => ['user_fr', 'user_fr', Role::Admin, false],
    'suspended person cannot change roles' => ['user_suspended', 'user_fr', Role::Admin, false],
]);

// --- Editing and offboarding people ----------------------------------------

it('controls who can edit a person', function (string $actor, string $target, bool $expected) {
    expect(can($this->world, $actor, 'update', $this->world->person($target)))->toBe($expected);
})->with([
    'owner can edit anyone' => ['owner', 'super_admin', true],
    'super admin can edit an admin' => ['super_admin', 'admin_fr', true],
    'super admin can edit themselves' => ['super_admin', 'super_admin', true],
    'super admin cannot edit the owner' => ['super_admin', 'owner', false],
    'France admin can edit a France user' => ['admin_fr', 'user_fr', true],
    'France admin cannot edit a UK user' => ['admin_fr', 'user_uk', false],
    'France admin cannot edit another admin' => ['admin_uk', 'admin_fr', false],
    'admin cannot edit a super admin' => ['admin_uk', 'super_admin', false],
    'user cannot edit anyone' => ['user_fr', 'freelancer_fr', false],
    'user cannot edit themselves (details are managed by admins)' => ['user_fr', 'user_fr', false],
]);

it('controls who can offboard a person', function (string $actor, string $target, bool $expected) {
    expect(can($this->world, $actor, 'offboard', $this->world->person($target)))->toBe($expected);
})->with([
    'owner can offboard a super admin' => ['owner', 'super_admin', true],
    'nobody can offboard the owner' => ['super_admin', 'owner', false],
    'owner cannot offboard themselves' => ['owner', 'owner', false],
    'super admin can offboard an admin' => ['super_admin', 'admin_uk', true],
    'France admin can offboard a France freelancer' => ['admin_fr', 'freelancer_fr', true],
    'France admin cannot offboard a UK user' => ['admin_fr', 'user_uk', false],
    'admin cannot offboard another admin' => ['admin_fr', 'admin_uk', false],
    'user cannot offboard anyone' => ['user_fr', 'freelancer_fr', false],
]);

it('never hard-deletes people, whoever asks', function (string $actor) {
    expect(can($this->world, $actor, 'delete', $this->world->person('user_fr')))->toBeFalse();
})->with(['owner', 'super_admin', 'admin_fr']);

// --- Sensitive permissions and entity scope --------------------------------

it('lets only leadership grant sensitive-field access', function (string $actor, string $target, bool $expected) {
    $target = $this->world->person($target);

    expect(can($this->world, $actor, 'grantSensitive', $target))->toBe($expected)
        ->and(can($this->world, $actor, 'changeEntityScope', $target))->toBe($expected);
})->with([
    'owner can grant to an admin' => ['owner', 'admin_fr', true],
    'super admin can grant to a user' => ['super_admin', 'user_fr', true],
    'super admin cannot grant to another super admin' => ['super_admin', 'super_admin', false],
    'admin cannot grant, even inside their entity' => ['admin_fr', 'user_fr', false],
    'user cannot grant' => ['user_fr', 'user_fr', false],
    'nobody can grant to the owner' => ['super_admin', 'owner', false],
]);

it('treats an expired sensitive grant as no grant', function () {
    $adminUk = $this->world->person('admin_uk');

    expect($adminUk->hasSensitiveAccess(SensitiveField::DealValues, $this->world->entities['UK']->id))->toBeFalse();
});

it('lets people see their own grants but not other people\'s', function () {
    $userFr = $this->world->person('user_fr');
    $others = app(AccessContext::class)->runAs($userFr, fn () => SensitiveGrant::query()->pluck('person_id')->unique()->all());

    expect($others)->toBe([$userFr->id]);
});

// --- Clients and projects ---------------------------------------------------

it('controls who can create projects and clients in an entity', function (string $actor, string $entity, bool $expected) {
    $entity = $this->world->entities[$entity];

    expect(can($this->world, $actor, 'create', [Project::class, $entity]))->toBe($expected)
        ->and(can($this->world, $actor, 'create', [Client::class, $entity]))->toBe($expected);
})->with([
    'super admin in any entity' => ['super_admin', 'US', true],
    'France admin in France' => ['admin_fr', 'FR', true],
    'France admin not in UK' => ['admin_fr', 'UK', false],
    'UK admin in extra entity US' => ['admin_uk', 'US', true],
    'user cannot create' => ['user_fr', 'FR', false],
    'freelancer cannot create' => ['freelancer_fr', 'FR', false],
]);

it('controls who can edit and delete projects', function (string $actor, string $project, bool $canEdit, bool $canDelete) {
    $project = $this->world->projects[$project];

    expect(can($this->world, $actor, 'update', $project))->toBe($canEdit)
        ->and(can($this->world, $actor, 'delete', $project))->toBe($canDelete);
})->with([
    'super admin edits and deletes anything' => ['super_admin', 'A_FR', true, true],
    'France admin edits France project, cannot delete' => ['admin_fr', 'A_FR', true, false],
    'France admin cannot edit UK project' => ['admin_fr', 'A_UK', false, false],
    'user on the project can view but not edit' => ['user_fr', 'A_FR', false, false],
]);

it('lets only people with the deal-values permission edit a budget', function (string $actor, bool $expected) {
    expect(can($this->world, $actor, 'update', $this->world->financials['A_FR']))->toBe($expected);
})->with([
    'super admin' => ['super_admin', true],
    'France admin with France grant' => ['admin_fr', true],
    'user on the project without a France grant' => ['user_fr', false],
]);

it('keeps entity settings leadership-only and never deletable in the app', function () {
    $france = $this->world->entities['FR'];

    expect(can($this->world, 'super_admin', 'update', $france))->toBeTrue()
        ->and(can($this->world, 'admin_fr', 'update', $france))->toBeFalse()
        ->and(can($this->world, 'owner', 'delete', $france))->toBeFalse();
});

// --- Inactive people ---------------------------------------------------------

it('refuses every action to suspended, offboarded or expired people', function (string $state) {
    $person = match ($state) {
        'suspended' => Person::factory()->admin()->suspended()->in($this->world->entities['FR'])->create(),
        'offboarded' => Person::factory()->admin()->offboarded()->in($this->world->entities['FR'])->create(),
        'expired' => Person::factory()->admin()->freelancer('-1 minute')->in($this->world->entities['FR'])->create(),
    };

    $gate = Gate::forUser($person);

    expect($gate->allows('view', $this->world->projects['A_FR']))->toBeFalse()
        ->and($gate->allows('update', $this->world->projects['A_FR']))->toBeFalse()
        ->and($gate->allows('create', [Project::class, $this->world->entities['FR']]))->toBeFalse()
        ->and($gate->allows('view', $person))->toBeFalse();
})->with(['suspended', 'offboarded', 'expired']);
