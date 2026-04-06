<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE event_model DROP CONSTRAINT IF EXISTS event_model_casting_status_check");
        DB::statement("ALTER TABLE event_model ADD CONSTRAINT event_model_casting_status_check CHECK (casting_status::text = ANY (ARRAY['scheduled', 'checked_in', 'selected', 'no_show', 'rejected']))");
    }

    public function down(): void
    {
        // Revertir rejected → no_show antes de quitar el valor
        DB::table('event_model')->where('casting_status', 'rejected')->update(['casting_status' => 'no_show']);

        DB::statement("ALTER TABLE event_model DROP CONSTRAINT IF EXISTS event_model_casting_status_check");
        DB::statement("ALTER TABLE event_model ADD CONSTRAINT event_model_casting_status_check CHECK (casting_status::text = ANY (ARRAY['scheduled', 'checked_in', 'selected', 'no_show']))");
    }
};
