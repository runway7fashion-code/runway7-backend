<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('complementary_guests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('guest_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('host_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('type'); // sponsor_guest, designer_guest, giveaway, other
            $table->foreignId('event_id')->nullable()->constrained('events')->nullOnDelete();
            $table->foreignId('event_day_id')->nullable()->constrained('event_days')->nullOnDelete();
            $table->foreignId('show_id')->nullable()->constrained('shows')->nullOnDelete();
            $table->string('notes')->nullable();
            $table->timestamps();

            $table->unique('guest_user_id');
            $table->index(['host_user_id', 'type']);
            $table->index('event_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('complementary_guests');
    }
};
