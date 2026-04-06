<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lead_activities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lead_id')->constrained('designer_leads')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('type'); // call, email, meeting, note, status_change, assignment, system
            $table->string('title');
            $table->text('description')->nullable();
            $table->timestamp('scheduled_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->string('status')->default('pending'); // pending, completed, cancelled
            $table->timestamps();

            $table->index(['lead_id', 'scheduled_at']);
            $table->index(['user_id', 'scheduled_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lead_activities');
    }
};
