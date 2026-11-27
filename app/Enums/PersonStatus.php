<?php

namespace App\Enums;

enum PersonStatus: string
{
    case Active = 'active';
    case Suspended = 'suspended';
    case Offboarded = 'offboarded';
}
