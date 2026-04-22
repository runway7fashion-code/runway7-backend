<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sponsorship_companies', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('website')->nullable();
            $table->string('instagram')->nullable();
            $table->string('logo')->nullable();
            $table->string('industry')->nullable();
            $table->string('country')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

        // Unique case-insensitive index on name (Postgres)
        DB::statement('CREATE UNIQUE INDEX sponsorship_companies_name_ci_unique ON sponsorship_companies (LOWER(name)) WHERE deleted_at IS NULL');
    }

    public function down(): void
    {
        Schema::dropIfExists('sponsorship_companies');
    }
};
