<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('accounting:update-overdue')->dailyAt('06:00');
