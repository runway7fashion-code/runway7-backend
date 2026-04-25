<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('conversation_user_state', function (Blueprint $table) {
            // NULL = not muted. Future timestamp = muted until that time.
            // Far-future date (e.g. 2099-12-31) is treated as "muted forever".
            $table->timestamp('muted_until')->nullable()->after('pinned_at');
        });
    }

    public function down(): void
    {
        Schema::table('conversation_user_state', function (Blueprint $table) {
            $table->dropColumn('muted_until');
        });
    }
};
