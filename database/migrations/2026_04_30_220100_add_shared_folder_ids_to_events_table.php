<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->string('shared_runway_logo_folder_id', 100)->nullable()->after('materials_deadline_default');
            $table->string('shared_hair_moodboard_folder_id', 100)->nullable()->after('shared_runway_logo_folder_id');
            $table->string('shared_makeup_moodboard_folder_id', 100)->nullable()->after('shared_hair_moodboard_folder_id');
        });
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->dropColumn([
                'shared_runway_logo_folder_id',
                'shared_hair_moodboard_folder_id',
                'shared_makeup_moodboard_folder_id',
            ]);
        });
    }
};
