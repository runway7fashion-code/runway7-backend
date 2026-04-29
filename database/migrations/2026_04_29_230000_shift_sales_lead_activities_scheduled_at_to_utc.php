<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Hasta hoy, el modelo `App\Models\LeadActivity` (sales) tenía un accessor custom
 * que leía `scheduled_at` asumiendo America/Lima TZ, y un setter custom que
 * guardaba el value crudo (sin conversión a UTC). Combinado con que el frontend
 * de sales/leads/show.vue tampoco convertía a UTC antes de enviar, los registros
 * existentes están almacenados como hora Perú LITERAL en una columna `timestamp
 * without time zone`.
 *
 * Ahora eliminamos el accessor y el frontend convierte a UTC ISO. Eloquent leerá
 * los nuevos registros como UTC. Los viejos seguirían siendo Perú literal y se
 * mostrarían 5h tarde en el frontend (porque el browser convertiría su literal
 * UTC a Perú = -5h).
 *
 * Esta migración suma 5 horas a `scheduled_at` y `completed_at` de los registros
 * existentes, convirtiéndolos efectivamente de "Perú literal" a "UTC literal" —
 * mismo wall-clock para el usuario después del switch.
 *
 * Solo aplica a `lead_activities` (sales). Sponsorship y `calendar_activities`
 * tenían cast 'datetime' standard y el frontend ya hacía la conversión, así que
 * sus registros ya están en UTC literal.
 */
return new class extends Migration
{
    /**
     * Cutoff: el commit ebac0f6 deployado 2026-04-29 23:11 UTC. A partir de ahí
     * el frontend de sales/leads/show.vue ya convierte local→UTC, así que
     * cualquier registro con created_at >= ese momento ya está en UTC literal
     * y NO debe migrarse. Los registros previos están en Perú literal y sí.
     */
    private const CUTOFF = '2026-04-29 23:11:13';

    public function up(): void
    {
        DB::statement("
            UPDATE lead_activities
            SET scheduled_at = scheduled_at + INTERVAL '5 hours'
            WHERE scheduled_at IS NOT NULL
              AND created_at < ?
        ", [self::CUTOFF]);
        DB::statement("
            UPDATE lead_activities
            SET completed_at = completed_at + INTERVAL '5 hours'
            WHERE completed_at IS NOT NULL
              AND created_at < ?
        ", [self::CUTOFF]);
    }

    public function down(): void
    {
        DB::statement("
            UPDATE lead_activities
            SET scheduled_at = scheduled_at - INTERVAL '5 hours'
            WHERE scheduled_at IS NOT NULL
              AND created_at < ?
        ", [self::CUTOFF]);
        DB::statement("
            UPDATE lead_activities
            SET completed_at = completed_at - INTERVAL '5 hours'
            WHERE completed_at IS NOT NULL
              AND created_at < ?
        ", [self::CUTOFF]);
    }
};
