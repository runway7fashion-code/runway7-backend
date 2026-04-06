<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('media_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('category', 20); // videographer, photographer
            $table->string('portfolio_url', 500)->nullable();
            $table->string('instagram', 255)->nullable();
            $table->string('location', 255)->nullable();
            $table->string('will_travel', 10)->default('yes'); // yes, no
            $table->tinyInteger('importance')->default(2); // 1, 2, 3
            $table->integer('max_assistants')->default(0);
            $table->string('media_link_1', 500)->nullable();
            $table->string('media_link_2', 500)->nullable();
            $table->string('media_link_3', 500)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('media_profiles');
    }
};
