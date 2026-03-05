<?php

namespace App\Console\Commands;

use App\Models\EventDay;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class CloseCastingDay extends Command
{
    protected $signature = 'casting:close-day';

    protected $description = 'Cierra el día de casting: checked_in → completed, scheduled → no_show';

    public function handle(): int
    {
        $yesterday = Carbon::yesterday()->toDateString();

        // Buscar días de casting que fueron ayer
        $castingDays = EventDay::where('type', 'casting')
            ->whereDate('date', $yesterday)
            ->with('event')
            ->get();

        if ($castingDays->isEmpty()) {
            $this->info('No hay días de casting que cerrar para ' . $yesterday);
            return self::SUCCESS;
        }

        $completedCount = 0;
        $noShowCount = 0;

        foreach ($castingDays as $day) {
            // checked_in → completed
            $completed = DB::table('event_model')
                ->where('event_id', $day->event_id)
                ->where('casting_status', 'checked_in')
                ->update(['casting_status' => 'completed']);

            // scheduled → no_show
            $noShow = DB::table('event_model')
                ->where('event_id', $day->event_id)
                ->where('casting_status', 'scheduled')
                ->whereNotNull('casting_time')
                ->update(['casting_status' => 'no_show']);

            $completedCount += $completed;
            $noShowCount += $noShow;

            $this->info("Evento: {$day->event->name} — {$completed} completadas, {$noShow} no-show");
        }

        $this->info("Total: {$completedCount} completadas, {$noShowCount} no-show");

        return self::SUCCESS;
    }
}
