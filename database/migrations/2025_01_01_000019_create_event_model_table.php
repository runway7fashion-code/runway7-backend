<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('event_model', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('model_id')->constrained('users')->cascadeOnDelete();
            $table->integer('participation_number')->nullable();
            $table->time('casting_time')->nullable();
            $table->timestamp('casting_checked_in_at')->nullable();
            $table->enum('casting_status', ['scheduled', 'checked_in', 'in_progress', 'completed', 'no_show'])->default('scheduled');
            $table->enum('status', ['invited', 'confirmed', 'rejected', 'checked_in'])->default('invited');
            $table->timestamp('checked_in_at')->nullable();
            $table->timestamps();

            $table->unique(['event_id', 'model_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_model');
    }
};
