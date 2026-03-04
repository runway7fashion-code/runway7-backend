<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Add 'fitting' to event_days type enum
        DB::statement("ALTER TABLE event_days DROP CONSTRAINT IF EXISTS event_days_type_check");
        DB::statement("ALTER TABLE event_days ADD CONSTRAINT event_days_type_check CHECK (type::text = ANY (ARRAY['setup','casting','show_day','ceremony','other','fitting']::text[]))");

        // 2. Add fitting configuration fields to event_days
        Schema::table('event_days', function (Blueprint $table) {
            $table->time('fitting_start')->nullable()->after('end_time');
            $table->time('fitting_end')->nullable()->after('fitting_start');
            $table->integer('fitting_interval')->nullable()->after('fitting_end');
        });

        // 3. Create fitting_slots table (similar to casting_slots)
        Schema::create('fitting_slots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_day_id')->constrained()->cascadeOnDelete();
            $table->time('time');
            $table->integer('capacity')->default(5);
            $table->timestamps();

            $table->unique(['event_day_id', 'time']);
        });

        // 4. Create fitting_assignments (designer → fitting slot)
        Schema::create('fitting_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fitting_slot_id')->constrained()->cascadeOnDelete();
            $table->foreignId('designer_id')->constrained('users')->cascadeOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['fitting_slot_id', 'designer_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fitting_assignments');
        Schema::dropIfExists('fitting_slots');

        Schema::table('event_days', function (Blueprint $table) {
            $table->dropColumn(['fitting_start', 'fitting_end', 'fitting_interval']);
        });

        DB::statement("ALTER TABLE event_days DROP CONSTRAINT IF EXISTS event_days_type_check");
        DB::statement("ALTER TABLE event_days ADD CONSTRAINT event_days_type_check CHECK (type::text = ANY (ARRAY['setup','casting','show_day','ceremony','other']::text[]))");
    }
};
