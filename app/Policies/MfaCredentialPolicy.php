<?php

namespace App\Policies;

use App\Models\MfaCredential;
use App\Models\Person;

/**
 * Second-factor secrets belong to their owner alone. Admins reset someone's
 * MFA through offboarding/re-enrolment (step 5), never by reading it.
 */
class MfaCredentialPolicy
{
    public function view(Person $viewer, MfaCredential $credential): bool
    {
        return $viewer->id === $credential->person_id;
    }

    public function update(Person $viewer, MfaCredential $credential): bool
    {
        return $viewer->id === $credential->person_id;
    }

    public function delete(Person $viewer, MfaCredential $credential): bool
    {
        return false;
    }
}
