<?php

namespace App\Policies;

use App\Enums\Role;
use App\Models\Entity;
use App\Models\Person;
use App\Models\Project;

class ProjectPolicy
{
    public function viewAny(Person $viewer): bool
    {
        return true; // the list itself is filtered to what the viewer may see
    }

    public function view(Person $viewer, Project $project): bool
    {
        return $viewer->isLeadership()
            || ($viewer->role === Role::Admin && $viewer->canSeeEntity($project->entity_id))
            || $viewer->isProjectMember($project->id);
    }

    public function create(Person $viewer, Entity $entity): bool
    {
        return $viewer->isLeadership()
            || ($viewer->role === Role::Admin && $viewer->canSeeEntity($entity->id));
    }

    public function update(Person $viewer, Project $project): bool
    {
        return $viewer->isLeadership()
            || ($viewer->role === Role::Admin && $viewer->canSeeEntity($project->entity_id));
    }

    /** Add or remove people on the project. */
    public function manageMembers(Person $viewer, Project $project): bool
    {
        return $this->update($viewer, $project);
    }

    public function delete(Person $viewer, Project $project): bool
    {
        return $viewer->isLeadership();
    }
}
