<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sponsorship_registrations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lead_id')->nullable()->constrained('sponsorship_leads')->nullOnDelete();
            $table->foreignId('sponsor_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('company_id')->constrained('sponsorship_companies')->cascadeOnDelete();
            $table->foreignId('event_id')->constrained('events')->cascadeOnDelete();
            $table->foreignId('package_id')->nullable()->constrained('sponsorship_packages')->nullOnDelete();
            $table->decimal('agreed_price', 10, 2)->default(0);
            $table->decimal('downpayment', 10, 2)->default(0);
            $table->unsignedSmallInteger('installments_count')->default(1);
            $table->text('notes')->nullable();
            $table->string('status')->default('registered'); // registered, onboarded, confirmed, cancelled
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('onboarding_email_sent_at')->nullable();
            $table->timestamps();

            $table->unique(['sponsor_user_id', 'event_id']);
            $table->index('company_id');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sponsorship_registrations');
    }
};
