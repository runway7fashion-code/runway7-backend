<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('moodboard_item_files', function (Blueprint $table) {
            $table->id();
            $table->foreignId('moodboard_item_id')->constrained('material_moodboard_items')->cascadeOnDelete();
            $table->string('drive_file_id', 100);
            $table->string('drive_url', 500)->nullable();
            $table->string('file_name', 255);
            $table->string('mime_type', 100)->nullable();
            $table->bigInteger('file_size')->nullable();
            $table->foreignId('uploaded_by')->constrained('users');
            $table->timestamps();

            $table->index('moodboard_item_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('moodboard_item_files');
    }
};
