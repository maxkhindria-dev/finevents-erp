<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/*
| Pest configuration: every Feature test boots the full Laravel app with a
| fresh database (SQLite in memory locally, MySQL 8 in CI).
*/

pest()->extend(TestCase::class)->use(RefreshDatabase::class)->in('Feature');
