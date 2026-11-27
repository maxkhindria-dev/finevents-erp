<?php

/*
| FinEvents-specific settings that are NOT secrets.
| The module list drives the navigation shell; later phases switch modules on
| and permission checks decide who sees each one.
*/

return [

    // Dedicated key for field-level encryption (AES-256-GCM). Required in
    // production; generate with: php artisan finevents:generate-field-key
    'field_encryption_key' => env('FIELD_ENCRYPTION_KEY'),

    // Secret key that seals each audit log entry to the previous one (HMAC-SHA256).
    // Kept outside the database so the log can't be silently rewritten from it.
    // Required in production; generate with: php artisan finevents:generate-field-key
    'audit_chain_key' => env('AUDIT_CHAIN_KEY'),

    // Where the nightly audit archive is written. In production this must be
    // write-once storage in the EU (e.g. S3 Object Lock, compliance mode).
    'audit_archive_disk' => env('AUDIT_ARCHIVE_DISK', 'local'),

    // Database connection for sensitive tables (budgets now; bank details,
    // rates, salaries and tokens later). In production this is a separate
    // MySQL login that can read them; the main app login cannot.
    'sensitive_connection' => env('DB_SENSITIVE_CONNECTION'),

    // Host part of the MySQL accounts that finevents:db:privileges grants to.
    'db_account_host' => env('DB_ACCOUNT_HOST', '%'),

    // Only Google accounts from this Workspace domain may sign in. If it is not
    // set, every sign-in is refused (fail closed).
    'workspace_domain' => env('GOOGLE_WORKSPACE_DOMAIN'),

    // Sign-in rules (CLAUDE.md §5.1).
    'session_max_hours' => 12,       // signed out 12 hours after signing in, whatever happens
    'mfa_fresh_minutes' => 10,       // sensitive actions need an MFA check this recent
    'mfa_issuer' => 'FinEvents ERP', // name shown in authenticator apps

    'brand' => [
        'blue' => '#002F55',
        'orange' => '#F57634',
    ],

    'modules' => [
        ['key' => 'crm', 'label' => 'CRM', 'phase' => 2],
        ['key' => 'time', 'label' => 'Time', 'phase' => 3],
        ['key' => 'projects', 'label' => 'Projects', 'phase' => 4],
        ['key' => 'finance', 'label' => 'Finance', 'phase' => 5],
        ['key' => 'time-off', 'label' => 'Time off', 'phase' => 6],
        ['key' => 'wiki', 'label' => 'Wiki', 'phase' => 7],
        ['key' => 'brand-tools', 'label' => 'Brand tools', 'phase' => null], // after v1
    ],

];
