<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('designer_displays', function (Blueprint $table) {
            $table->id();
            $table->foreignId('designer_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('show_id')->nullable()->constrained()->nullOnDelete();
            $table->string('background_video_url')->nullable();
            $table->string('music_audio_url')->nullable();
            $table->enum('status', ['pending', 'ready', 'confirmed'])->default('pending');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['designer_id', 'event_id', 'show_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('designer_displays');
    }
};
