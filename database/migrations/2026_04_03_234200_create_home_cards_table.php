<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('home_cards', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('image_url');
            $table->enum('action_type', ['url', 'video', 'mailto'])->default('url');
            $table->string('action_value');
            $table->json('target_roles')->nullable();
            $table->foreignId('event_id')->nullable()->constrained()->nullOnDelete();
            $table->integer('order')->default(0);
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('home_cards');
    }
};
