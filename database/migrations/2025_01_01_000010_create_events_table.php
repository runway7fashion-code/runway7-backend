<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('events', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('city');
            $table->string('venue')->nullable();
            $table->string('timezone')->default('America/New_York');
            $table->date('start_date');
            $table->date('end_date');
            $table->enum('status', ['draft', 'published', 'active', 'completed', 'cancelled'])->default('draft');
            $table->json('settings')->nullable();
            $table->text('description')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('events');
    }
};
