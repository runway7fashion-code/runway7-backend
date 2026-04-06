<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Update status CHECK constraint to formally include rejected and no_show
        DB::statement("ALTER TABLE event_staff DROP CONSTRAINT IF EXISTS event_staff_status_check");
        DB::statement("ALTER TABLE event_staff ADD CONSTRAINT event_staff_status_check CHECK (status::text = ANY (ARRAY['assigned'::text, 'checked_in'::text, 'completed'::text, 'rejected'::text, 'no_show'::text]))");

        // 2. Remove casting_status column (not applicable to volunteers)
        Schema::table('event_staff', function (Blueprint $table) {
            $table->dropColumn('casting_status');
        });
    }

    public function down(): void
    {
        // Restore casting_status column
        Schema::table('event_staff', function (Blueprint $table) {
            $table->string('casting_status', 20)->nullable()->default('scheduled');
        });

        // Restore original status CHECK constraint (without rejected and no_show)
        DB::statement("ALTER TABLE event_staff DROP CONSTRAINT IF EXISTS event_staff_status_check");
        DB::statement("ALTER TABLE event_staff ADD CONSTRAINT event_staff_status_check CHECK (status::text = ANY (ARRAY['assigned'::text, 'checked_in'::text, 'completed'::text]))");
    }
};
