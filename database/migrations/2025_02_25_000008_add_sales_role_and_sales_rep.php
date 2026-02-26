<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Add 'sales' to role constraint
        DB::statement("ALTER TABLE users DROP CONSTRAINT IF EXISTS users_role_check");
        DB::statement("ALTER TABLE users ADD CONSTRAINT users_role_check CHECK (role::text = ANY (ARRAY['admin','accounting','operation','tickets_manager','marketing','public_relations','sales','designer','model','media','volunteer','staff','attendee','vip','influencer','press','sponsor','complementary']::text[]))");

        // Add sales_rep_id to designer_profiles
        Schema::table('designer_profiles', function (Blueprint $table) {
            $table->foreignId('sales_rep_id')->nullable()->constrained('users')->nullOnDelete()->after('category_id');
        });
    }

    public function down(): void
    {
        Schema::table('designer_profiles', function (Blueprint $table) {
            $table->dropConstrainedForeignId('sales_rep_id');
        });

        DB::statement("ALTER TABLE users DROP CONSTRAINT IF EXISTS users_role_check");
        DB::statement("ALTER TABLE users ADD CONSTRAINT users_role_check CHECK (role::text = ANY (ARRAY['admin','accounting','operation','tickets_manager','marketing','public_relations','designer','model','media','volunteer','staff','attendee','vip','influencer','press','sponsor','complementary']::text[]))");
    }
};
