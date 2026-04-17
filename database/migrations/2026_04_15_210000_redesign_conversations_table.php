<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Drop old constraints and columns
        Schema::table('conversations', function (Blueprint $table) {
            $table->dropForeign(['model_id']);
            $table->dropForeign(['designer_id']);
            $table->dropForeign(['show_id']);
            $table->dropUnique(['model_id', 'designer_id', 'show_id']);
            $table->dropIndex(['model_id', 'status']);
            $table->dropIndex(['designer_id', 'status']);
        });

        // Remove check constraint on status
        DB::statement("ALTER TABLE conversations DROP CONSTRAINT IF EXISTS conversations_status_check");

        Schema::table('conversations', function (Blueprint $table) {
            // Rename existing columns
            $table->renameColumn('model_id', 'user_a_id');
            $table->renameColumn('designer_id', 'user_b_id');
        });

        Schema::table('conversations', function (Blueprint $table) {
            // Make show_id nullable (not all conversations are about shows)
            $table->bigInteger('show_id')->nullable()->change();

            // Add new columns
            $table->string('context_type', 30)->nullable()->after('show_id'); // 'casting', 'material', null (general)
            $table->unsignedBigInteger('context_id')->nullable()->after('context_type'); // FK to related entity
            $table->string('status', 20)->default('active')->change();

            // New foreign keys
            $table->foreign('user_a_id')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('user_b_id')->references('id')->on('users')->cascadeOnDelete();

            // New indexes
            $table->index(['user_a_id', 'status']);
            $table->index(['user_b_id', 'status']);
            $table->index(['context_type', 'context_id']);
        });
    }

    public function down(): void
    {
        Schema::table('conversations', function (Blueprint $table) {
            $table->dropForeign(['user_a_id']);
            $table->dropForeign(['user_b_id']);
            $table->dropIndex(['user_a_id', 'status']);
            $table->dropIndex(['user_b_id', 'status']);
            $table->dropIndex(['context_type', 'context_id']);
            $table->dropColumn(['context_type', 'context_id']);
        });

        Schema::table('conversations', function (Blueprint $table) {
            $table->renameColumn('user_a_id', 'model_id');
            $table->renameColumn('user_b_id', 'designer_id');
        });

        Schema::table('conversations', function (Blueprint $table) {
            $table->foreign('model_id')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('designer_id')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('show_id')->references('id')->on('shows')->cascadeOnDelete();
            $table->unique(['model_id', 'designer_id', 'show_id']);
            $table->index(['model_id', 'status']);
            $table->index(['designer_id', 'status']);
        });
    }
};
