<?php

namespace App\Console\Commands;

use App\Services\SalesBotService;
use Illuminate\Console\Command;

class SalesBotCheck extends Command
{
    protected $signature = 'sales:bot-check';
    protected $description = 'Run sales bot checks: overdue activities, upcoming reminders, stale leads';

    public function handle(): void
    {
        $bot = new SalesBotService();

        $overdue = $bot->checkOverdueActivities();
        $this->info("Overdue activities notified: {$overdue}");

        $upcoming = $bot->checkUpcomingActivities();
        $this->info("Upcoming reminders sent: {$upcoming}");

        $stale = $bot->checkStaleLeads();
        $this->info("Stale leads alerted: {$stale}");
    }
}
