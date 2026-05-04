<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('event_shared_materials', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->string('material_name', 100);
            $table->string('drive_file_id', 100);
            $table->string('drive_url', 500)->nullable();
            $table->string('file_name', 255);
            $table->string('mime_type', 100)->nullable();
            $table->bigInteger('file_size')->nullable();
            $table->foreignId('uploaded_by')->constrained('users');
            $table->timestamps();

            $table->unique(['event_id', 'material_name', 'drive_file_id'], 'event_shared_materials_unique');
            $table->index(['event_id', 'material_name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_shared_materials');
    }
};
