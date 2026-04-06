<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sales_registrations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sales_rep_id')->constrained('users');
            $table->foreignId('designer_id')->constrained('users');
            $table->foreignId('event_id')->constrained('events');
            $table->foreignId('package_id')->nullable()->constrained('designer_packages');
            $table->decimal('agreed_price', 10, 2)->default(0);
            $table->text('notes')->nullable();
            $table->string('status')->default('registered'); // registered, in_review, onboarded, confirmed, cancelled
            $table->timestamp('onboarded_at')->nullable();
            $table->foreignId('onboarded_by')->nullable()->constrained('users');
            $table->timestamp('confirmed_at')->nullable();
            $table->foreignId('confirmed_by')->nullable()->constrained('users');
            $table->timestamps();

            $table->unique(['designer_id', 'event_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sales_registrations');
    }
};
