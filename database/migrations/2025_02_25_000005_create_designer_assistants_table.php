<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('designer_assistants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('designer_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->string('full_name');
            $table->string('document_id')->nullable();
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->enum('status', ['registered', 'checked_in'])->default('registered');
            $table->timestamp('checked_in_at')->nullable();
            $table->timestamps();

            $table->index(['designer_id', 'event_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('designer_assistants');
    }
};
