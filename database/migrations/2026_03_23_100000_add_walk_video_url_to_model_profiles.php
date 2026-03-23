<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('model_profiles', function (Blueprint $table) {
            $table->string('walk_video_url', 500)->nullable()->after('referral_source_other');
        });
    }

    public function down(): void
    {
        Schema::table('model_profiles', function (Blueprint $table) {
            $table->dropColumn('walk_video_url');
        });
    }
};
