<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('designer_payment_plans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('designer_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('package_id')->nullable()->constrained('designer_packages')->nullOnDelete();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->decimal('total_amount', 10, 2);
            $table->decimal('downpayment', 10, 2)->default(0);
            $table->decimal('remaining_amount', 10, 2);
            $table->integer('installments_count')->default(1);
            $table->enum('downpayment_status', ['pending', 'paid'])->default('pending');
            $table->string('downpayment_receipt')->nullable();
            $table->timestamp('downpayment_paid_at')->nullable();
            $table->enum('status', ['active', 'completed', 'cancelled'])->default('active');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['designer_id', 'event_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('designer_payment_plans');
    }
};
