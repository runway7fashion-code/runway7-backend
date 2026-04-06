<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Migrate interested → follow_up in designer_leads
        DB::table('designer_leads')
            ->where('status', 'interested')
            ->update(['status' => 'follow_up']);

        // Migrate interested → follow_up in lead_events
        DB::table('lead_events')
            ->where('status', 'interested')
            ->update(['status' => 'follow_up']);
    }

    public function down(): void {}
};
