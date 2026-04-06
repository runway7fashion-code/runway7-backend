<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Migrar datos existentes: in_progress → checked_in
        DB::table('event_model')->where('casting_status', 'in_progress')->update(['casting_status' => 'checked_in']);

        DB::statement("ALTER TABLE event_model DROP CONSTRAINT IF EXISTS event_model_casting_status_check");
        DB::statement("ALTER TABLE event_model ADD CONSTRAINT event_model_casting_status_check CHECK (casting_status::text = ANY (ARRAY['scheduled', 'checked_in', 'completed', 'no_show']))");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE event_model DROP CONSTRAINT IF EXISTS event_model_casting_status_check");
        DB::statement("ALTER TABLE event_model ADD CONSTRAINT event_model_casting_status_check CHECK (casting_status::text = ANY (ARRAY['scheduled', 'checked_in', 'in_progress', 'completed', 'no_show']))");
    }
};
