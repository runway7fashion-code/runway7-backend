<?php

namespace App\Console\Commands;

use App\Models\EventPass;
use App\Models\User;
use App\Services\ModelService;
use Illuminate\Console\Command;

class GenerateMissingPasses extends Command
{
    protected $signature = 'passes:generate-missing';
    protected $description = 'Genera pases faltantes para modelos asignadas a eventos sin pase';

    public function handle(ModelService $modelService): int
    {
        $models = User::models()
            ->with(['eventsAsModelWithCasting', 'eventPasses'])
            ->get();

        $generated = 0;
        $adminId = User::where('role', 'admin')->first()?->id ?? 1;

        foreach ($models as $model) {
            $existingPassEventIds = $model->eventPasses->pluck('event_id')->toArray();

            foreach ($model->eventsAsModelWithCasting as $event) {
                if (!in_array($event->id, $existingPassEventIds)) {
                    $modelService->syncModelPass($model, $event->id, $adminId);
                    $generated++;
                    $this->line("  Pase generado: {$model->full_name} → {$event->name}");
                }
            }
        }

        $this->info("Listo. {$generated} pases generados.");

        return self::SUCCESS;
    }
}
