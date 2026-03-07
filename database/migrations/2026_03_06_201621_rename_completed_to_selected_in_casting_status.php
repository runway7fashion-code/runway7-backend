<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Migrar datos existentes: completed → selected
        DB::table('event_model')->where('casting_status', 'completed')->update(['casting_status' => 'selected']);

        // Actualizar constraint del enum
        DB::statement("ALTER TABLE event_model DROP CONSTRAINT IF EXISTS event_model_casting_status_check");
        DB::statement("ALTER TABLE event_model ADD CONSTRAINT event_model_casting_status_check CHECK (casting_status::text = ANY (ARRAY['scheduled', 'checked_in', 'selected', 'no_show']))");
    }

    public function down(): void
    {
        DB::table('event_model')->where('casting_status', 'selected')->update(['casting_status' => 'completed']);

        DB::statement("ALTER TABLE event_model DROP CONSTRAINT IF EXISTS event_model_casting_status_check");
        DB::statement("ALTER TABLE event_model ADD CONSTRAINT event_model_casting_status_check CHECK (casting_status::text = ANY (ARRAY['scheduled', 'checked_in', 'completed', 'no_show']))");
    }
};
