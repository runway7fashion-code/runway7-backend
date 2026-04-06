<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $tags = [
            ['name' => 'Des. Team Follow Up', 'color' => '#9CA3AF'],
            ['name' => 'Latam', 'color' => '#5EEAD4'],
            ['name' => 'NA', 'color' => '#9CA3AF'],
            ['name' => 'Des. Team', 'color' => '#FBBF24'],
            ['name' => 'Dead', 'color' => '#F87171'],
            ['name' => 'Interested / Follow Up', 'color' => '#4ADE80'],
            ['name' => 'On Board Feb 2026', 'color' => '#FBBF24'],
            ['name' => 'On Board Sept 2026', 'color' => '#93C5FD'],
            ['name' => 'On Board Miami 2026', 'color' => '#C4B5FD'],
            ['name' => 'On Board LA 2026', 'color' => '#FDBA74'],
            ['name' => 'OS / Contract', 'color' => '#4ADE80'],
            ['name' => 'ADs Feb 2026', 'color' => '#93C5FD'],
            ['name' => 'Past Season', 'color' => '#F0ABFC'],
            ['name' => 'On hold', 'color' => '#F87171'],
            ['name' => 'Re-Connect', 'color' => '#FDBA74'],
            ['name' => 'Paid Sponsor', 'color' => '#FDBA74'],
            ['name' => 'Interested / Next Season', 'color' => '#C4B5FD'],
            ['name' => 'Wrong Number', 'color' => '#F87171'],
            ['name' => 'ARG', 'color' => '#93C5FD'],
            ['name' => 'CALENDLY', 'color' => '#FDBA74'],
            ['name' => 'Website Form', 'color' => '#9CA3AF'],
            ['name' => 'In Kind Sponsors', 'color' => '#E5E7EB'],
            ['name' => 'Accessories', 'color' => '#FDBA74'],
            ['name' => 'Not Budget', 'color' => '#F87171'],
            ['name' => 'Invited – Free', 'color' => '#4ADE80'],
            ['name' => 'Courtesy Tickets', 'color' => '#A78BFA'],
            ['name' => 'Past Lead (AD)', 'color' => '#9CA3AF'],
            ['name' => 'VIP Response – Interested', 'color' => '#FBBF24'],
            ['name' => 'ADs Sept 2026', 'color' => '#86EFAC'],
            ['name' => 'BLACKLIST', 'color' => '#6B7280'],
            ['name' => 'IG REQUEST', 'color' => '#4ADE80'],
            ['name' => 'Twilio', 'color' => '#86EFAC'],
            ['name' => 'Mailerlite', 'color' => '#F0ABFC'],
            ['name' => 'Ads Tour 2026', 'color' => '#86EFAC'],
            ['name' => 'On board Sept 2027', 'color' => '#9CA3AF'],
            ['name' => 'On board Feb 2027', 'color' => '#4ADE80'],
        ];

        $now = now();
        foreach ($tags as $tag) {
            DB::table('lead_tags')->insert(array_merge($tag, [
                'created_at' => $now,
                'updated_at' => $now,
            ]));
        }
    }

    public function down(): void
    {
        DB::table('lead_tags')->truncate();
    }
};
