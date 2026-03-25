<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('event_designer', function (Blueprint $table) {
            $table->boolean('media_package')->default(false)->after('model_casting_enabled');
            $table->boolean('custom_background')->default(false)->after('media_package');
            $table->boolean('courtesy_tickets')->default(false)->after('custom_background');
        });
    }

    public function down(): void
    {
        Schema::table('event_designer', function (Blueprint $table) {
            $table->dropColumn(['media_package', 'custom_background', 'courtesy_tickets']);
        });
    }
};
