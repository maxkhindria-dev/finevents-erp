<?php

namespace App\Policies;

use App\Enums\Role;
use App\Models\Entity;
use App\Models\Person;

/**
 * Who may see and manage people (CLAUDE.md §2).
 * Inactive people are refused everything before these rules run (see AppServiceProvider).
 */
class PersonPolicy
{
    public function viewAny(Person $viewer): bool
    {
        return $viewer->role->atLeast(Role::Admin);
    }

    public function view(Person $viewer, Person $person): bool
    {
        return $viewer->isLeadership()
            || $viewer->id === $person->id
            || ($viewer->role === Role::Admin && $viewer->canSeeEntity($person->primary_entity_id));
    }

    /** Invite a new person into an entity. New people always start as User. */
    public function create(Person $viewer, Entity $entity): bool
    {
        return $viewer->isLeadership()
            || ($viewer->role === Role::Admin && $viewer->canSeeEntity($entity->id));
    }

    /** Edit name, team, freelancer expiry and similar details. */
    public function update(Person $viewer, Person $person): bool
    {
        return match ($viewer->role) {
            Role::Owner => true,
            Role::SuperAdmin => $viewer->id === $person->id
                || in_array($person->role, [Role::Admin, Role::User], true),
            Role::Admin => $person->role === Role::User && $viewer->canSeeEntity($person->primary_entity_id),
            Role::User => false,
        };
    }

    /**
     * Change someone's role.
     *  - Nobody can create, remove or change the Owner from inside the app.
     *  - Nobody can change their own role.
     *  - The Owner manages Super Admins; Super Admins manage Admins and Users;
     *    Admins cannot change roles.
     */
    public function changeRole(Person $viewer, Person $person, Role $newRole): bool
    {
        if ($viewer->id === $person->id || $person->role === Role::Owner || $newRole === Role::Owner) {
            return false;
        }

        return match ($viewer->role) {
            Role::Owner => true,
            Role::SuperAdmin => in_array($person->role, [Role::Admin, Role::User], true)
                && in_array($newRole, [Role::Admin, Role::User], true),
            default => false,
        };
    }

    /** Grant or remove access to extra entities. */
    public function changeEntityScope(Person $viewer, Person $person): bool
    {
        return $this->manageAsLeadership($viewer, $person);
    }

    /** Grant sensitive-field access (bank details, rates, salaries, deal values). */
    public function grantSensitive(Person $viewer, Person $person): bool
    {
        return $this->manageAsLeadership($viewer, $person);
    }

    /** One-click offboarding: revokes sessions and connections. */
    public function offboard(Person $viewer, Person $person): bool
    {
        if ($viewer->id === $person->id || $person->role === Role::Owner) {
            return false;
        }

        return match ($viewer->role) {
            Role::Owner => true,
            Role::SuperAdmin => in_array($person->role, [Role::Admin, Role::User], true),
            Role::Admin => $person->role === Role::User && $viewer->canSeeEntity($person->primary_entity_id),
            Role::User => false,
        };
    }

    /** People are never hard-deleted; they are offboarded (and later anonymised). */
    public function delete(Person $viewer, Person $person): bool
    {
        return false;
    }

    private function manageAsLeadership(Person $viewer, Person $person): bool
    {
        if ($viewer->id === $person->id || $person->role === Role::Owner) {
            return false;
        }

        return match ($viewer->role) {
            Role::Owner => true,
            Role::SuperAdmin => $person->role !== Role::SuperAdmin,
            default => false,
        };
    }
}
