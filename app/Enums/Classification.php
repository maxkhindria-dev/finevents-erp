<?php

namespace App\Enums;

/**
 * Data classification carried by every record (CLAUDE.md §5.3).
 * Client and financial data is "restricted".
 */
enum Classification: string
{
    case Public = 'public';
    case Internal = 'internal';
    case Confidential = 'confidential';
    case Restricted = 'restricted';
}
