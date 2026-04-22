<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sponsorship_package_benefits', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('sponsorship_package_benefit', function (Blueprint $table) {
            $table->id();
            $table->foreignId('package_id')->constrained('sponsorship_packages')->cascadeOnDelete();
            $table->foreignId('benefit_id')->constrained('sponsorship_package_benefits')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['package_id', 'benefit_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sponsorship_package_benefit');
        Schema::dropIfExists('sponsorship_package_benefits');
    }
};
