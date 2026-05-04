<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('accounting:update-overdue')->dailyAt('06:00');
Schedule::command('accounting:send-installment-reminders')->dailyAt('09:00');
Schedule::command('casting:close-day')->dailyAt('00:05');
Schedule::command('casting:process-invitations')->everyMinute()->withoutOverlapping();

// Sales and sponsorship bots: in-app feed only (every 5 min). No emails.
Schedule::command('sales:bot-check')->everyFiveMinutes();
Schedule::command('sponsorship:bot-check')->everyFiveMinutes();

Schedule::command('materials:send-deadline-reminders')->dailyAt('09:00');
