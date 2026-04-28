<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Standalone calendar entries (calls / meetings / notes) NOT tied to a lead.
 *
 * Use case: a leader (e.g. Christina) blocks her own calendar with personal
 * activities, or schedules generic admin calls. The same table serves both
 * Sponsorship and Sales calendars; the `area` column lets each panel filter.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('calendar_activities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete()->comment('Assigned to');
            $table->foreignId('created_by_user_id')->constrained('users')->cascadeOnDelete();
            $table->string('area', 20)->nullable()->comment('sales / sponsorship / null');
            $table->string('type', 20)->comment('call / meeting / note');
            $table->string('title');
            $table->text('description')->nullable();
            $table->timestamp('scheduled_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->string('status', 20)->default('pending');
            $table->timestamps();

            $table->index(['user_id', 'scheduled_at']);
            $table->index(['area', 'scheduled_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('calendar_activities');
    }
};
