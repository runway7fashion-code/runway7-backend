<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sponsorship_registration_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('registration_id')->constrained('sponsorship_registrations')->cascadeOnDelete();
            $table->foreignId('uploaded_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('type')->default('other'); // contract, payment_proof, other
            $table->string('original_name');
            $table->string('path');
            $table->string('mime_type')->nullable();
            $table->unsignedBigInteger('size')->nullable();
            $table->string('note')->nullable();
            $table->timestamps();

            $table->index('registration_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sponsorship_registration_documents');
    }
};
