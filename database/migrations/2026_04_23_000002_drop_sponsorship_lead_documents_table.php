<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('sponsorship_lead_documents');
    }

    public function down(): void
    {
        Schema::create('sponsorship_lead_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lead_id')->constrained('sponsorship_leads')->cascadeOnDelete();
            $table->foreignId('uploaded_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('original_name');
            $table->string('path');
            $table->string('mime_type')->nullable();
            $table->unsignedBigInteger('size')->nullable();
            $table->string('note')->nullable();
            $table->timestamps();

            $table->index('lead_id');
        });
    }
};
