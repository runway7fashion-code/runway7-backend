<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales_registrations', function (Blueprint $table) {
            $table->dropForeign('sales_registrations_designer_id_foreign');
            $table->foreign('designer_id')->references('id')->on('users')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::table('sales_registrations', function (Blueprint $table) {
            $table->dropForeign(['designer_id']);
            $table->foreign('designer_id')->references('id')->on('users');
        });
    }
};
