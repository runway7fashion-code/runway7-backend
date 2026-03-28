<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lead_events', function (Blueprint $table) {
            $table->string('status')->default('new')->after('event_id');
        });

        // Copy the lead's global status to each lead_event
        $leads = DB::table('designer_leads')->get(['id', 'status']);
        foreach ($leads as $lead) {
            DB::table('lead_events')
                ->where('lead_id', $lead->id)
                ->update(['status' => $lead->status]);
        }
    }

    public function down(): void
    {
        Schema::table('lead_events', function (Blueprint $table) {
            $table->dropColumn('status');
        });
    }
};
