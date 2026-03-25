<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('event_volunteer', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('volunteer_id')->constrained('users')->cascadeOnDelete();
            $table->string('assigned_role', 255)->nullable();
            $table->string('status', 255)->default('assigned');
            $table->timestamp('checked_in_at')->nullable();
            $table->text('notes')->nullable();
            $table->string('area', 255)->nullable();
            $table->timestamps();

            $table->unique(['event_id', 'volunteer_id']);
        });

        // Migrate data from event_staff where user role = 'volunteer'
        DB::statement("
            INSERT INTO event_volunteer (event_id, volunteer_id, assigned_role, status, checked_in_at, notes, area, created_at, updated_at)
            SELECT es.event_id, es.user_id, es.assigned_role, es.status, es.checked_in_at, es.notes, es.area, es.created_at, es.updated_at
            FROM event_staff es
            JOIN users u ON u.id = es.user_id
            WHERE u.role = 'volunteer'
        ");

        // Add CHECK constraint
        DB::statement("ALTER TABLE event_volunteer ADD CONSTRAINT event_volunteer_status_check CHECK (status::text = ANY (ARRAY['assigned', 'checked_in', 'completed', 'rejected', 'no_show']))");
    }

    public function down(): void
    {
        Schema::dropIfExists('event_volunteer');
    }
};
