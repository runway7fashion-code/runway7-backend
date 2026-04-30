<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Permite que call/meeting tengan duración explícita (`ends_at`). Cuando es null
 * la actividad es un punto en el tiempo y no genera bloqueo. Cuando está set,
 * el rango [scheduled_at, ends_at] bloquea cualquier otra actividad del user
 * que se solape (back-to-back permitido).
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['lead_activities', 'sponsorship_lead_activities', 'calendar_activities'] as $table) {
            Schema::table($table, function (Blueprint $tbl) {
                $tbl->timestamp('ends_at')->nullable()->after('scheduled_at');
            });
        }
    }

    public function down(): void
    {
        foreach (['lead_activities', 'sponsorship_lead_activities', 'calendar_activities'] as $table) {
            Schema::table($table, function (Blueprint $tbl) {
                $tbl->dropColumn('ends_at');
            });
        }
    }
};
