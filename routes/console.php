<?php

use Illuminate\Support\Facades\Schedule;

// Keep a week of trips ready on every depot's trip board.
// Run `php artisan schedule:work` (or a cron / Task Scheduler entry for `schedule:run`).
Schedule::command('srmss:generate-trips --days=7')->dailyAt('00:05');
