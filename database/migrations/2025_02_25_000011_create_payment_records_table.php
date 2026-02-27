<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('designer_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->decimal('amount', 10, 2);
            $table->enum('payment_type', ['downpayment', 'installment']);
            $table->string('payment_method');
            $table->string('reference')->nullable();
            $table->string('receipt_url')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('registered_by')->constrained('users')->cascadeOnDelete();
            $table->timestamp('payment_date');
            $table->timestamps();

            $table->index(['event_id', 'payment_date']);
            $table->index(['designer_id', 'event_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_records');
    }
};
