<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('accounting:update-overdue')->dailyAt('06:00');
Schedule::command('casting:close-day')->dailyAt('00:05');

// Sales bot: in-app every 5 min + email digest every weekday at 9 AM Lima time.
Schedule::command('sales:bot-check')->everyFiveMinutes();
Schedule::command('sales:bot-digest')->weekdays()->timezone('America/Lima')->dailyAt('09:00');

// Sponsorship bot: same cadence, separate from sales (uses partnerships@ inbox).
Schedule::command('sponsorship:bot-check')->everyFiveMinutes();
Schedule::command('sponsorship:bot-digest')->weekdays()->timezone('America/Lima')->dailyAt('09:00');

Schedule::command('materials:send-deadline-reminders')->dailyAt('09:00');
