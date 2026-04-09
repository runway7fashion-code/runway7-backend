<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('designer_leads', function (Blueprint $table) {
            $table->string('redirect_type', 20)->nullable()->after('source');        // model, media, volunteer
            $table->string('redirect_status', 20)->nullable()->after('redirect_type'); // new, converted, rejected
            $table->string('redirect_note', 500)->nullable()->after('redirect_status');
            $table->foreignId('redirected_by')->nullable()->after('redirect_note')->constrained('users');
            $table->timestamp('redirected_at')->nullable()->after('redirected_by');
            $table->foreignId('converted_user_id')->nullable()->after('redirected_at')->constrained('users'); // the created model/media/volunteer user
        });
    }

    public function down(): void
    {
        Schema::table('designer_leads', function (Blueprint $table) {
            $table->dropForeign(['redirected_by']);
            $table->dropForeign(['converted_user_id']);
            $table->dropColumn(['redirect_type', 'redirect_status', 'redirect_note', 'redirected_by', 'redirected_at', 'converted_user_id']);
        });
    }
};
