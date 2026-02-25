<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('show_designer', function (Blueprint $table) {
            $table->id();
            $table->foreignId('show_id')->constrained()->cascadeOnDelete();
            $table->foreignId('designer_id')->constrained('users')->cascadeOnDelete();
            $table->integer('order')->default(0);
            $table->string('collection_name')->nullable();
            $table->enum('status', ['assigned', 'confirmed', 'cancelled'])->default('assigned');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['show_id', 'designer_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('show_designer');
    }
};
