<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('material_moodboard_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('material_id')->constrained('designer_materials')->cascadeOnDelete();
            $table->foreignId('uploaded_by')->constrained('users');
            $table->string('drive_file_id', 100)->nullable();
            $table->string('drive_url', 500)->nullable();
            $table->string('image_name')->nullable();
            $table->text('response_text')->nullable(); // Designer's text response
            $table->timestamp('responded_at')->nullable();
            $table->integer('order')->default(0);
            $table->timestamps();

            $table->index('material_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('material_moodboard_items');
    }
};
