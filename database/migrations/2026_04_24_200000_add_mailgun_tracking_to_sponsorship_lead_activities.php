<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sponsorship_lead_activities', function (Blueprint $table) {
            $table->string('mailgun_message_id')->nullable()->index()->after('is_contract');
            $table->string('delivery_status', 40)->nullable()->after('mailgun_message_id');
            $table->text('delivery_error')->nullable()->after('delivery_status');
            $table->timestamp('delivered_at')->nullable()->after('delivery_error');
        });
    }

    public function down(): void
    {
        Schema::table('sponsorship_lead_activities', function (Blueprint $table) {
            $table->dropIndex(['mailgun_message_id']);
            $table->dropColumn(['mailgun_message_id', 'delivery_status', 'delivery_error', 'delivered_at']);
        });
    }
};
