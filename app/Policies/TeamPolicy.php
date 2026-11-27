<?php

namespace App\Policies;

use App\Models\Entity;
use App\Models\Person;
use App\Models\Team;

class TeamPolicy
{
    public function viewAny(Person $viewer): bool
    {
        return true; // filtered to the viewer's entities
    }

    public function view(Person $viewer, Team $team): bool
    {
        return $viewer->canSeeEntity($team->entity_id);
    }

    public function create(Person $viewer, Entity $entity): bool
    {
        return $viewer->isLeadership();
    }

    public function update(Person $viewer, Team $team): bool
    {
        return $viewer->isLeadership();
    }

    public function delete(Person $viewer, Team $team): bool
    {
        return $viewer->isLeadership();
    }
}
