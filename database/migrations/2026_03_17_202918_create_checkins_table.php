<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('checkins', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('event_day_id')->constrained()->cascadeOnDelete();
            $table->enum('type', ['entry', 'exit', 'single'])->default('single');
            $table->timestamp('checked_at');
            $table->enum('method', ['kiosk', 'manual'])->default('kiosk');
            $table->string('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['event_id', 'event_day_id']);
            $table->index(['user_id', 'event_day_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('checkins');
    }
};
