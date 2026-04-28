<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds the `extra_areas` JSONB column on users to grant secondary cross-area
 * management access without hardcoding by name. Example: a Sales leader who
 * also handles Sponsorship (Christina) gets extra_areas = ["sponsorship"].
 *
 * Lookups: User::canManageArea($area) and User::isLeaderOf($area) consult
 * role + sponsorship_type/sales_type first, then fall back to extra_areas.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->jsonb('extra_areas')->nullable()->after('sponsorship_type');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('extra_areas');
        });
    }
};
