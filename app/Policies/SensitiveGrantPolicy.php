<?php

namespace App\Policies;

use App\Models\Person;
use App\Models\SensitiveGrant;

class SensitiveGrantPolicy
{
    public function viewAny(Person $viewer): bool
    {
        return true; // leadership sees all grants, others only their own
    }

    public function view(Person $viewer, SensitiveGrant $grant): bool
    {
        return $viewer->isLeadership() || $viewer->id === $grant->person_id;
    }

    /** Creating a grant is checked with PersonPolicy::grantSensitive. */
    public function create(Person $viewer): bool
    {
        return $viewer->isLeadership();
    }

    public function delete(Person $viewer, SensitiveGrant $grant): bool
    {
        return $viewer->isLeadership() && $viewer->id !== $grant->person_id;
    }
}
