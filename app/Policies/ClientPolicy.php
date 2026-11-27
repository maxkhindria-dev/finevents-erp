<?php

namespace App\Policies;

use App\Enums\Role;
use App\Models\Client;
use App\Models\Entity;
use App\Models\Person;

class ClientPolicy
{
    public function viewAny(Person $viewer): bool
    {
        return true; // the list itself is filtered to what the viewer may see
    }

    public function view(Person $viewer, Client $client): bool
    {
        return $viewer->isLeadership()
            || ($viewer->role === Role::Admin && $viewer->canSeeEntity($client->entity_id))
            || $viewer->isClientMember($client->id);
    }

    public function create(Person $viewer, Entity $entity): bool
    {
        return $viewer->isLeadership()
            || ($viewer->role === Role::Admin && $viewer->canSeeEntity($entity->id));
    }

    public function update(Person $viewer, Client $client): bool
    {
        return $viewer->isLeadership()
            || ($viewer->role === Role::Admin && $viewer->canSeeEntity($client->entity_id));
    }

    public function delete(Person $viewer, Client $client): bool
    {
        return $viewer->isLeadership();
    }
}
