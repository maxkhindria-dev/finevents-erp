<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/*
| Pest configuration: every Feature test boots the full Laravel app with a
| fresh database (SQLite in memory locally, MySQL 8 in CI).
*/

pest()->extend(TestCase::class)->use(RefreshDatabase::class)->in('Feature');

// Layer 3 database-permission tests: real MySQL logins, no transaction wrapping.
pest()->extend(TestCase::class)->in('Database');
