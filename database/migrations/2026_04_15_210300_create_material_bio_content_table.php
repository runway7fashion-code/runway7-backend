<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('material_bio_content', function (Blueprint $table) {
            $table->id();
            $table->foreignId('material_id')->constrained('designer_materials')->cascadeOnDelete();
            $table->text('biography')->nullable();
            $table->text('collection_description')->nullable();
            $table->text('additional_notes')->nullable();
            $table->text('contact_info')->nullable();
            $table->timestamps();

            $table->unique('material_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('material_bio_content');
    }
};
