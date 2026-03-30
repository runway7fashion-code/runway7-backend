<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Map old lead statuses to new marketing statuses
        // contacted, follow_up, negotiating → qualified (are real prospects)
        DB::table('designer_leads')
            ->whereIn('status', ['contacted', 'follow_up', 'negotiating'])
            ->update(['status' => 'qualified']);

        // converted → client
        DB::table('designer_leads')
            ->where('status', 'converted')
            ->update(['status' => 'client']);

        // no_response, no_contact → lost
        DB::table('designer_leads')
            ->whereIn('status', ['no_response', 'no_contact'])
            ->update(['status' => 'lost']);

        // new, spam, lost stay the same
    }

    public function down(): void {}
};
