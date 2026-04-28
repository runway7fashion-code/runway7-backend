<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sponsorship_leads', function (Blueprint $table) {
            $table->string('last_email_type', 30)->nullable()->after('last_email_status');
        });

        Schema::table('sponsorship_lead_activities', function (Blueprint $table) {
            $table->string('email_type', 30)->nullable()->after('is_contract');
        });
    }

    public function down(): void
    {
        Schema::table('sponsorship_leads', function (Blueprint $table) {
            $table->dropColumn('last_email_type');
        });

        Schema::table('sponsorship_lead_activities', function (Blueprint $table) {
            $table->dropColumn('email_type');
        });
    }
};
