<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('support_cases', function (Blueprint $table) {
            $table->id();
            $table->string('case_number')->unique();
            $table->foreignId('designer_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('event_id')->nullable()->constrained()->nullOnDelete();
            $table->enum('channel', ['email', 'sms', 'phone', 'whatsapp', 'dm']);
            $table->enum('case_type', ['claim', 'complaint', 'payment', 'refund']);
            $table->string('contact_email')->nullable();
            $table->date('claim_date');
            $table->enum('status', ['open', 'in_progress', 'resolved', 'closed'])->default('open');
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->timestamps();

            $table->index(['designer_id', 'status']);
            $table->index(['event_id']);
            $table->index('case_number');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('support_cases');
    }
};
