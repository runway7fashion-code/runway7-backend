<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // event_designer: invited/confirmed/rejected → confirmed/cancelled
        DB::statement('ALTER TABLE event_designer DROP CONSTRAINT IF EXISTS event_designer_status_check');
        DB::statement("ALTER TABLE event_designer ADD CONSTRAINT event_designer_status_check
            CHECK (status IN ('confirmed', 'cancelled'))");

        // show_designer: assigned/confirmed/cancelled → confirmed/cancelled
        // Primero migrar datos: 'assigned' → 'confirmed'
        DB::table('show_designer')->where('status', 'assigned')->update(['status' => 'confirmed']);

        DB::statement('ALTER TABLE show_designer DROP CONSTRAINT IF EXISTS show_designer_status_check');
        DB::statement("ALTER TABLE show_designer ADD CONSTRAINT show_designer_status_check
            CHECK (status IN ('confirmed', 'cancelled'))");
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE event_designer DROP CONSTRAINT IF EXISTS event_designer_status_check');
        DB::statement("ALTER TABLE event_designer ADD CONSTRAINT event_designer_status_check
            CHECK (status IN ('invited', 'confirmed', 'rejected'))");

        DB::statement('ALTER TABLE show_designer DROP CONSTRAINT IF EXISTS show_designer_status_check');
        DB::statement("ALTER TABLE show_designer ADD CONSTRAINT show_designer_status_check
            CHECK (status IN ('assigned', 'confirmed', 'cancelled'))");
    }
};
