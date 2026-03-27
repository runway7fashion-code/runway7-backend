<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('accounting:update-overdue')->dailyAt('06:00');
Schedule::command('casting:close-day')->dailyAt('00:05');
Schedule::command('sales:bot-check')->everyFiveMinutes();
