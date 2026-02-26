<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('designer_materials', function (Blueprint $table) {
            $table->id();
            $table->foreignId('designer_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('show_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('drive_link')->nullable();
            $table->enum('status', ['pending', 'submitted', 'confirmed', 'rejected'])->default('pending');
            $table->string('type');
            $table->integer('order')->default(0);
            $table->timestamps();

            $table->index(['designer_id', 'event_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('designer_materials');
    }
};
