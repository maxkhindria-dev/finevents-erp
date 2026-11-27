<?php

namespace App\Policies;

use App\Models\Entity;
use App\Models\Person;

class EntityPolicy
{
    public function viewAny(Person $viewer): bool
    {
        return true;
    }

    public function view(Person $viewer, Entity $entity): bool
    {
        return true;
    }

    /** Entity settings (currency, accounting system) are leadership-only. */
    public function update(Person $viewer, Entity $entity): bool
    {
        return $viewer->isLeadership();
    }

    /** New entities and deletions are a hosting-level change, not done in the app. */
    public function create(Person $viewer): bool
    {
        return false;
    }

    public function delete(Person $viewer, Entity $entity): bool
    {
        return false;
    }
}
