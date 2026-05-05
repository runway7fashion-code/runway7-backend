<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscription_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('subscription_id')->constrained()->cascadeOnDelete();
            $table->foreignId('payment_method_id')->nullable()
                ->constrained('subscription_payment_methods')->nullOnDelete();
            $table->decimal('amount', 12, 2);
            $table->date('paid_at');
            $table->date('period_start')->nullable();
            $table->date('period_end')->nullable();
            $table->string('receipt_path')->nullable();
            $table->string('invoice_url')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('registered_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['subscription_id', 'paid_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscription_payments');
    }
};
