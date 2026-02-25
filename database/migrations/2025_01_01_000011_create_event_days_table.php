<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('event_days', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->string('label');
            $table->enum('type', ['setup', 'casting', 'show_day', 'ceremony', 'other'])->default('show_day');
            $table->time('start_time')->nullable();
            $table->time('end_time')->nullable();
            $table->integer('order')->default(0);
            $table->enum('status', ['scheduled', 'active', 'completed'])->default('scheduled');
            $table->text('description')->nullable();
            $table->timestamps();

            $table->unique(['event_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_days');
    }
};
