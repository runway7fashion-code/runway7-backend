<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('communication_logs', function (Blueprint $table) {
            $table->text('message')->nullable()->after('channel');
            $table->integer('segments')->nullable()->after('message');
            $table->decimal('cost', 8, 4)->nullable()->after('segments');
            $table->timestamp('scheduled_at')->nullable()->after('sent_at');
        });
    }

    public function down(): void
    {
        Schema::table('communication_logs', function (Blueprint $table) {
            $table->dropColumn(['message', 'segments', 'cost', 'scheduled_at']);
        });
    }
};
