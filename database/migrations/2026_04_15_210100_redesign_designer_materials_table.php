<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Drop old status check constraint
        DB::statement("ALTER TABLE designer_materials DROP CONSTRAINT IF EXISTS designer_materials_status_check");

        Schema::table('designer_materials', function (Blueprint $table) {
            // New status field (varchar, not enum) to support both flows
            $table->string('status', 20)->default('pending')->change();

            // Status flow type: 'collaborative' (Estado 1) or 'simple' (Estado 2)
            $table->string('status_flow', 15)->default('simple')->after('status');

            // Google Drive integration
            $table->string('drive_folder_id', 100)->nullable()->after('drive_link');
            $table->string('drive_folder_url', 500)->nullable()->after('drive_folder_id');

            // Who can upload to this material
            $table->string('upload_by', 20)->default('designer')->after('type'); // designer, operation, tickets, system

            // Whether this material is read-only for the designer
            $table->boolean('is_readonly')->default(false)->after('upload_by');
        });
    }

    public function down(): void
    {
        Schema::table('designer_materials', function (Blueprint $table) {
            $table->dropColumn(['status_flow', 'drive_folder_id', 'drive_folder_url', 'upload_by', 'is_readonly']);
        });
    }
};
