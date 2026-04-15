<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('material_files', function (Blueprint $table) {
            $table->id();
            $table->foreignId('material_id')->constrained('designer_materials')->cascadeOnDelete();
            $table->foreignId('uploaded_by')->constrained('users');
            $table->string('drive_file_id', 100)->nullable();
            $table->string('drive_url', 500)->nullable();
            $table->string('file_name');
            $table->string('file_type', 50)->nullable(); // image, video, audio, pdf, url
            $table->string('mime_type', 100)->nullable();
            $table->unsignedBigInteger('file_size')->nullable(); // bytes
            $table->text('note')->nullable();
            $table->boolean('is_final')->default(false); // marks the final/approved version
            $table->timestamps();

            $table->index('material_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('material_files');
    }
};
