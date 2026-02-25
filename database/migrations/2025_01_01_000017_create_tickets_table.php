<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tickets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ticket_type_id')->constrained()->cascadeOnDelete();
            $table->string('buyer_first_name');
            $table->string('buyer_last_name');
            $table->string('buyer_email');
            $table->string('buyer_phone')->nullable();
            $table->string('qr_code')->unique();
            $table->enum('status', ['confirmed', 'checked_in', 'cancelled', 'refunded'])->default('confirmed');
            $table->enum('source', ['web', 'woocommerce', 'kiosk', 'manual'])->default('web');
            $table->string('external_order_id')->nullable();
            $table->json('check_times')->nullable();
            $table->timestamp('first_check_in_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['qr_code']);
            $table->index(['buyer_email']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tickets');
    }
};
