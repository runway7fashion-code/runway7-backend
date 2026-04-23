<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Clean up any lingering soft-deleted rows before dropping the column
        if (Schema::hasColumn('users', 'deleted_at')) {
            DB::table('users')->whereNotNull('deleted_at')->delete();
            Schema::table('users', function (Blueprint $table) {
                $table->dropSoftDeletes();
            });
        }

        if (Schema::hasColumn('designer_leads', 'deleted_at')) {
            DB::table('designer_leads')->whereNotNull('deleted_at')->delete();
            Schema::table('designer_leads', function (Blueprint $table) {
                $table->dropSoftDeletes();
            });
        }

        if (Schema::hasColumn('sponsorship_leads', 'deleted_at')) {
            DB::table('sponsorship_leads')->whereNotNull('deleted_at')->delete();
            Schema::table('sponsorship_leads', function (Blueprint $table) {
                $table->dropSoftDeletes();
            });
        }
    }

    public function down(): void
    {
        if (!Schema::hasColumn('users', 'deleted_at')) {
            Schema::table('users', function (Blueprint $table) {
                $table->softDeletes();
            });
        }

        if (!Schema::hasColumn('designer_leads', 'deleted_at')) {
            Schema::table('designer_leads', function (Blueprint $table) {
                $table->softDeletes();
            });
        }

        if (!Schema::hasColumn('sponsorship_leads', 'deleted_at')) {
            Schema::table('sponsorship_leads', function (Blueprint $table) {
                $table->softDeletes();
            });
        }
    }
};
