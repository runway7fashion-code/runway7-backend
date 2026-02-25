<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('casting_slots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_day_id')->constrained()->cascadeOnDelete();
            $table->time('time');
            $table->integer('capacity')->default(50);
            $table->integer('booked')->default(0);
            $table->timestamps();

            $table->unique(['event_day_id', 'time']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('casting_slots');
    }
};
