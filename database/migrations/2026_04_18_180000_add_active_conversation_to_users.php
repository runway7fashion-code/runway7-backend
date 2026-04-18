<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('active_conversation_id')->nullable()->constrained('conversations')->nullOnDelete();
            $table->timestamp('active_conversation_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('active_conversation_id');
            $table->dropColumn('active_conversation_at');
        });
    }
};
