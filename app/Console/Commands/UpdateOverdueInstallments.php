<?php

namespace App\Console\Commands;

use App\Services\AccountingService;
use Illuminate\Console\Command;

class UpdateOverdueInstallments extends Command
{
    protected $signature = 'accounting:update-overdue';

    protected $description = 'Marca como vencidas las cuotas pendientes/parciales cuya fecha de pago ya pasó';

    public function handle(AccountingService $service): int
    {
        $updated = $service->updateOverdueInstallments();

        $this->info("Cuotas actualizadas a vencidas: {$updated}");

        return self::SUCCESS;
    }
}
