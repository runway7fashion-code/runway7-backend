<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sponsorship_lead_activities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lead_id')->constrained('sponsorship_leads')->cascadeOnDelete();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('assigned_to_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('type'); // call, email, meeting, note, status_change, assignment, system
            $table->string('title');
            $table->text('description')->nullable();
            $table->timestamp('scheduled_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->string('status')->default('pending'); // pending, completed, cancelled, not_completed
            $table->boolean('is_contract')->default(false); // email con contrato
            $table->timestamps();

            $table->index(['lead_id', 'scheduled_at']);
            $table->index(['assigned_to_user_id', 'scheduled_at']);
            $table->index(['created_by_user_id', 'scheduled_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sponsorship_lead_activities');
    }
};
