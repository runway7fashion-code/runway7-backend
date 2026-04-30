<?php

namespace App\Console\Commands;

use App\Services\SponsorshipBotService;
use Illuminate\Console\Command;

class SponsorshipBotDigest extends Command
{
    protected $signature = 'sponsorship:bot-digest';
    protected $description = 'Send the daily overdue activities digest email to each sponsorship advisor (one consolidated email per user).';

    public function handle(): void
    {
        $sent = (new SponsorshipBotService())->sendDailyOverdueDigest();
        $this->info("Sponsorship digest emails sent: {$sent}");
    }
}
