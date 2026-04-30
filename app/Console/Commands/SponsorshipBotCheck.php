<?php

namespace App\Console\Commands;

use App\Services\SponsorshipBotService;
use Illuminate\Console\Command;

class SponsorshipBotCheck extends Command
{
    protected $signature = 'sponsorship:bot-check';
    protected $description = 'Run sponsorship bot checks: in-app overdue, upcoming reminders, stale leads. Email is handled by sponsorship:bot-digest.';

    public function handle(): void
    {
        $bot = new SponsorshipBotService();

        $overdue = $bot->checkOverdueActivities();
        $this->info("In-app overdue notifications: {$overdue}");

        $upcoming = $bot->checkUpcomingActivities();
        $this->info("Upcoming reminders sent: {$upcoming}");

        $stale = $bot->checkStaleLeads();
        $this->info("Stale leads alerted: {$stale}");
    }
}
