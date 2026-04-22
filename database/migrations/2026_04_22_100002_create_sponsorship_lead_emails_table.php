<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sponsorship_lead_emails', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lead_id')->constrained('sponsorship_leads')->cascadeOnDelete();
            $table->string('email');
            $table->boolean('is_primary')->default(false);
            $table->timestamps();

            // Email único global en el módulo (un email no puede estar en 2 leads de sponsorship)
            $table->unique('email');
            $table->index(['lead_id', 'is_primary']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sponsorship_lead_emails');
    }
};
