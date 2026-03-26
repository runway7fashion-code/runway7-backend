<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('designer_leads', function (Blueprint $table) {
            $table->id();
            $table->string('first_name');
            $table->string('last_name');
            $table->string('email');
            $table->string('phone')->nullable();
            $table->string('country')->nullable();
            $table->string('company_name')->nullable();
            $table->string('retail_category')->nullable();
            $table->string('website_url')->nullable();
            $table->string('instagram')->nullable();
            $table->string('designs_ready')->nullable();
            $table->string('budget')->nullable();
            $table->string('past_shows')->nullable();
            $table->foreignId('event_id')->nullable()->constrained('events')->nullOnDelete();
            $table->string('preferred_contact_time')->nullable();
            $table->string('status')->default('new');
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('converted_designer_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('source')->default('website');
            $table->text('notes')->nullable();
            $table->timestamp('last_contacted_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('status');
            $table->index('assigned_to');
            $table->index('email');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('designer_leads');
    }
};
