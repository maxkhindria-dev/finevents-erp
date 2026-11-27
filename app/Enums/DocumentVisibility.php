<?php

namespace App\Enums;

enum DocumentVisibility: string
{
    /** Staff only. Freelancers never see it, even on their own projects. */
    case Internal = 'internal';

    /** Freelancers on the project or client may also see it. */
    case FreelancersAllowed = 'freelancers_allowed';
}
