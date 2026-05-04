<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('show_model', function (Blueprint $table) {
            $table->timestamp('notified_1h_at')->nullable()->after('expires_at');
            $table->timestamp('notified_30m_at')->nullable()->after('notified_1h_at');
            $table->timestamp('notified_5m_at')->nullable()->after('notified_30m_at');
        });
    }

    public function down(): void
    {
        Schema::table('show_model', function (Blueprint $table) {
            $table->dropColumn(['notified_1h_at', 'notified_30m_at', 'notified_5m_at']);
        });
    }
};
