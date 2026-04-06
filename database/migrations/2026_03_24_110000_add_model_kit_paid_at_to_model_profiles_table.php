<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('model_profiles', function (Blueprint $table) {
            $table->timestamp('model_kit_paid_at')->nullable()->after('wants_model_kit');
        });
    }

    public function down(): void
    {
        Schema::table('model_profiles', function (Blueprint $table) {
            $table->dropColumn('model_kit_paid_at');
        });
    }
};
