<?php

namespace App\Console\Commands;

use App\Models\EventDay;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class CloseCastingDay extends Command
{
    protected $signature = 'casting:close-day';

    protected $description = 'Cierra el día de casting: scheduled → no_show para modelos que no se presentaron';

    public function handle(): int
    {
        $yesterday = Carbon::yesterday()->toDateString();

        $castingDays = EventDay::where('type', 'casting')
            ->whereDate('date', $yesterday)
            ->with('event')
            ->get();

        if ($castingDays->isEmpty()) {
            $this->info('No hay días de casting que cerrar para ' . $yesterday);
            return self::SUCCESS;
        }

        $noShowCount = 0;

        foreach ($castingDays as $day) {
            // scheduled → no_show (modelos con horario asignado que no se presentaron)
            $noShow = DB::table('event_model')
                ->where('event_id', $day->event_id)
                ->where('casting_status', 'scheduled')
                ->whereNotNull('casting_time')
                ->update(['casting_status' => 'no_show']);

            $noShowCount += $noShow;

            $this->info("Evento: {$day->event->name} — {$noShow} no-show");
        }

        $this->info("Total: {$noShowCount} no-show");

        return self::SUCCESS;
    }
}
