<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('event_model', function (Blueprint $table) {
            $table->string('shopify_order_number')->nullable()->after('casting_status');
            $table->unique('shopify_order_number');
        });
    }

    public function down(): void
    {
        Schema::table('event_model', function (Blueprint $table) {
            $table->dropUnique(['shopify_order_number']);
            $table->dropColumn('shopify_order_number');
        });
    }
};
