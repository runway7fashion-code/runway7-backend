<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Activities can be scheduled for a day without a specific time. Useful when
 * the lead doesn't tell us a preferred time but we still want to remember to
 * follow up that day. All-day activities don't generate availability conflicts.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['lead_activities', 'sponsorship_lead_activities'] as $table) {
            Schema::table($table, function (Blueprint $tbl) {
                $tbl->boolean('all_day')->default(false)->after('ends_at');
            });
        }
    }

    public function down(): void
    {
        foreach (['lead_activities', 'sponsorship_lead_activities'] as $table) {
            Schema::table($table, function (Blueprint $tbl) {
                $tbl->dropColumn('all_day');
            });
        }
    }
};
