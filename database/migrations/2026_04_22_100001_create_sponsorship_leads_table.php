<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sponsorship_leads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('sponsorship_companies')->cascadeOnDelete();
            $table->string('first_name');
            $table->string('last_name');
            $table->string('phone')->nullable();
            $table->string('charge')->nullable();
            $table->string('linkedin_url')->nullable();
            $table->string('website_url')->nullable();
            $table->string('instagram')->nullable();
            $table->foreignId('category_id')->nullable()->constrained('sponsorship_categories')->nullOnDelete();

            $table->string('status')->default('nuevo');
            $table->string('source')->default('website');
            $table->string('source_detail')->nullable();

            $table->foreignId('registered_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('assigned_to_user_id')->nullable()->constrained('users')->nullOnDelete();

            $table->boolean('is_contract_winner')->default(false);
            $table->foreignId('converted_user_id')->nullable()->constrained('users')->nullOnDelete();

            $table->text('notes')->nullable();
            $table->timestamp('last_contacted_at')->nullable();
            $table->timestamp('last_email_sent_at')->nullable();
            $table->string('last_email_status')->nullable(); // sent | failed

            $table->timestamps();
            $table->softDeletes();

            $table->index('status');
            $table->index('assigned_to_user_id');
            $table->index('company_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sponsorship_leads');
    }
};
