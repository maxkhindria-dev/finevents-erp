<?php

use Illuminate\Support\Facades\Schedule;

/*
| Scheduled jobs (times in UTC). Laravel Cloud runs the scheduler every minute.
*/

// Check every audit seal, then copy new entries to write-once storage.
Schedule::command('finevents:audit:verify')->dailyAt('02:10')->onOneServer();
Schedule::command('finevents:audit:archive')->dailyAt('02:20')->onOneServer();
