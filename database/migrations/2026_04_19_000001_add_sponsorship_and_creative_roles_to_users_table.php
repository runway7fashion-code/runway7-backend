<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE users DROP CONSTRAINT IF EXISTS users_role_check');
        DB::statement("ALTER TABLE users ADD CONSTRAINT users_role_check CHECK (role IN (
            'admin','accounting','operation','tickets_manager','marketing','public_relations','sales','creative','sponsorship',
            'designer','model','media','volunteer','staff','assistant',
            'attendee','vip','influencer','press','sponsor','complementary'
        ))");
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE users DROP CONSTRAINT IF EXISTS users_role_check');
        DB::statement("ALTER TABLE users ADD CONSTRAINT users_role_check CHECK (role IN (
            'admin','accounting','operation','tickets_manager','marketing','public_relations','sales',
            'designer','model','media','volunteer','staff','assistant',
            'attendee','vip','influencer','press','sponsor','complementary'
        ))");
    }
};
