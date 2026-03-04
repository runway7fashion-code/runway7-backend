<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE users DROP CONSTRAINT IF EXISTS users_status_check");
        DB::statement("ALTER TABLE users ADD CONSTRAINT users_status_check CHECK (status::text = ANY (ARRAY['active', 'inactive', 'pending', 'applicant']))");
    }

    public function down(): void
    {
        DB::table('users')->where('status', 'applicant')->update(['status' => 'pending']);

        DB::statement("ALTER TABLE users DROP CONSTRAINT IF EXISTS users_status_check");
        DB::statement("ALTER TABLE users ADD CONSTRAINT users_status_check CHECK (status::text = ANY (ARRAY['active', 'inactive', 'pending']))");
    }
};
