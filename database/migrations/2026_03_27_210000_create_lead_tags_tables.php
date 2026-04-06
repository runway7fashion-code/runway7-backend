<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lead_tags', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('color')->default('#6B7280');
            $table->timestamps();
        });

        Schema::create('lead_tag', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lead_id')->constrained('designer_leads')->cascadeOnDelete();
            $table->foreignId('tag_id')->constrained('lead_tags')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['lead_id', 'tag_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lead_tag');
        Schema::dropIfExists('lead_tags');
    }
};
