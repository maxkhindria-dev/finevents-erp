<?php

use Tests\TestCase;

/*
| Pest configuration: every Feature test boots the full Laravel app.
| Later pull requests add RefreshDatabase and the permission-matrix helpers here.
*/

pest()->extend(TestCase::class)->in('Feature');
