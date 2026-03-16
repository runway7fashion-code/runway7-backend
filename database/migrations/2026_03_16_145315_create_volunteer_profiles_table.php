<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('volunteer_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->integer('age')->nullable();
            $table->string('gender', 20)->nullable();
            $table->string('tshirt_size', 10)->nullable();
            $table->string('experience', 50)->nullable();
            $table->string('comfortable_fast_paced', 20)->default('structured');
            $table->string('full_availability', 20)->default('yes');
            $table->text('contribution')->nullable();
            $table->string('resume_link')->nullable();
            $table->string('instagram')->nullable();
            $table->string('location')->nullable();
            $table->timestamps();

            $table->unique('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('volunteer_profiles');
    }
};
