<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sponsorship_lead_activity_files', function (Blueprint $table) {
            $table->id();
            $table->foreignId('activity_id')->constrained('sponsorship_lead_activities')->cascadeOnDelete();
            $table->string('file_path');
            $table->string('file_name');
            $table->unsignedBigInteger('size')->nullable();
            $table->string('mime_type')->nullable();
            $table->timestamps();

            $table->index('activity_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sponsorship_lead_activity_files');
    }
};
