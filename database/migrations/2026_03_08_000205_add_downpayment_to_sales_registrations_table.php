<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('sales_registrations', function (Blueprint $table) {
            $table->decimal('downpayment', 10, 2)->nullable()->after('agreed_price');
        });
    }

    public function down(): void
    {
        Schema::table('sales_registrations', function (Blueprint $table) {
            $table->dropColumn('downpayment');
        });
    }
};
