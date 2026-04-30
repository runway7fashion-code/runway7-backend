<?php

namespace App\Console\Commands;

use App\Services\SalesBotService;
use Illuminate\Console\Command;

class SalesBotDigest extends Command
{
    protected $signature = 'sales:bot-digest';
    protected $description = 'Send the daily overdue activities digest email to each sales advisor (one consolidated email per user).';

    public function handle(): void
    {
        $sent = (new SalesBotService())->sendDailyOverdueDigest();
        $this->info("Sales digest emails sent: {$sent}");
    }
}
