<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('designer_contact_emails', function (Blueprint $table) {
            $table->id();
            $table->foreignId('designer_id')->constrained('users')->cascadeOnDelete();
            $table->string('email');
            $table->string('label')->nullable();
            $table->timestamps();

            $table->index(['designer_id']);
            $table->unique(['designer_id', 'email']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('designer_contact_emails');
    }
};
