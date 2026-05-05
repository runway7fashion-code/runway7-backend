<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscriptions', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('vendor')->nullable();
            $table->text('description')->nullable();
            $table->string('account_email')->nullable();
            $table->string('department');
            $table->string('category');
            $table->enum('billing_cycle', ['monthly', 'quarterly', 'annual', 'one_time']);
            $table->decimal('amount', 12, 2);
            $table->foreignId('payment_method_id')->nullable()
                ->constrained('subscription_payment_methods')->nullOnDelete();
            $table->date('purchase_date')->nullable();
            $table->date('next_renewal_date')->nullable();
            $table->boolean('auto_renew')->default(true);
            $table->enum('status', ['active', 'paused', 'cancelled', 'trial'])->default('active');
            $table->string('plan_tier')->nullable();
            $table->unsignedInteger('seats')->nullable();
            $table->string('website_url')->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->text('cancellation_reason')->nullable();
            $table->timestamps();

            $table->index('next_renewal_date');
            $table->index('status');
            $table->index('department');
            $table->index('category');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscriptions');
    }
};
