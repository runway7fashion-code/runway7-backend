<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscription_payment_methods', function (Blueprint $table) {
            $table->id();
            $table->string('nickname');
            $table->enum('card_type', ['visa', 'mastercard']);
            $table->string('last_four', 4);
            $table->string('holder_name')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscription_payment_methods');
    }
};
