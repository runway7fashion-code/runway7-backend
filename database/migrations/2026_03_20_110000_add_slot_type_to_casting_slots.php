<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE casting_slots ADD COLUMN slot_type VARCHAR(10) NOT NULL DEFAULT 'normal'");
        DB::statement("ALTER TABLE casting_slots ADD CONSTRAINT casting_slots_slot_type_check CHECK (slot_type::text = ANY (ARRAY['normal', 'merch']))");

        // Drop old unique and create new one including slot_type
        DB::statement("ALTER TABLE casting_slots DROP CONSTRAINT IF EXISTS casting_slots_event_day_id_time_unique");
        DB::statement("CREATE UNIQUE INDEX casting_slots_event_day_id_time_slot_type_unique ON casting_slots (event_day_id, time, slot_type)");
    }

    public function down(): void
    {
        DB::statement("DROP INDEX IF EXISTS casting_slots_event_day_id_time_slot_type_unique");
        DB::statement("ALTER TABLE casting_slots DROP CONSTRAINT IF EXISTS casting_slots_slot_type_check");
        DB::statement("ALTER TABLE casting_slots DROP COLUMN IF EXISTS slot_type");
        DB::statement("CREATE UNIQUE INDEX casting_slots_event_day_id_time_unique ON casting_slots (event_day_id, time)");
    }
};
