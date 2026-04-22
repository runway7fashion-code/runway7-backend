<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sponsorship_lead_tag', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lead_id')->constrained('sponsorship_leads')->cascadeOnDelete();
            $table->foreignId('tag_id')->constrained('sponsorship_tags')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['lead_id', 'tag_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sponsorship_lead_tag');
    }
};
