<?php

namespace App\Enums;

/**
 * Sensitive fields need an explicit grant per entity (CLAUDE.md §2),
 * except for the Owner and Super Admins, who see them everywhere.
 */
enum SensitiveField: string
{
    case BankDetails = 'bank_details';
    case DayRates = 'day_rates';
    case Salaries = 'salaries';
    case DealValues = 'deal_values'; // also covers project budgets
}
