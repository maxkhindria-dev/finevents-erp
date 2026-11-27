<?php

namespace App\Policies;

use App\Models\AuditEvent;
use App\Models\Person;

/**
 * The audit log can be read by the Owner and Super Admins, and changed by
 * nobody, the Owner included (CLAUDE.md §2 and §5.5).
 */
class AuditEventPolicy
{
    public function viewAny(Person $viewer): bool
    {
        return $viewer->isLeadership();
    }

    public function view(Person $viewer, AuditEvent $event): bool
    {
        return $viewer->isLeadership();
    }

    public function create(Person $viewer): bool
    {
        return false;
    }

    public function update(Person $viewer, AuditEvent $event): bool
    {
        return false;
    }

    public function delete(Person $viewer, AuditEvent $event): bool
    {
        return false;
    }
}
