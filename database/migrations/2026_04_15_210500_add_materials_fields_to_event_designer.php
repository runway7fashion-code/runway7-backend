<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('event_designer', function (Blueprint $table) {
            $table->date('materials_deadline')->nullable()->after('notes');
            $table->string('drive_root_folder_id', 100)->nullable()->after('materials_deadline');
            $table->string('drive_root_folder_url', 500)->nullable()->after('drive_root_folder_id');
        });
    }

    public function down(): void
    {
        Schema::table('event_designer', function (Blueprint $table) {
            $table->dropColumn(['materials_deadline', 'drive_root_folder_id', 'drive_root_folder_url']);
        });
    }
};
