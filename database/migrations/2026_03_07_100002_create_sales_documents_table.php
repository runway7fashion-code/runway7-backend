<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sales_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sales_registration_id')->constrained('sales_registrations')->cascadeOnDelete();
            $table->foreignId('uploaded_by')->constrained('users');
            $table->string('type'); // contract, payment_proof, other
            $table->string('file_path');
            $table->string('original_name');
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sales_documents');
    }
};
