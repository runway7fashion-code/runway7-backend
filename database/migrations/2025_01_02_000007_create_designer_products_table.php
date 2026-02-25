<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('designer_products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('designer_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('event_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->decimal('price', 10, 2);
            $table->decimal('compare_price', 10, 2)->nullable();
            $table->json('images')->nullable();
            $table->json('sizes_available')->nullable();
            $table->json('colors_available')->nullable();
            $table->string('category')->nullable();
            $table->integer('stock')->default(0);
            $table->enum('status', ['draft', 'active', 'sold_out', 'hidden'])->default('draft');
            $table->boolean('featured')->default(false);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['designer_id', 'status']);
            $table->index(['event_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('designer_products');
    }
};
