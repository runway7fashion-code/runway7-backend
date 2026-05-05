<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('event_media', function (Blueprint $table) {
            $table->string('kit_type', 20)->nullable()->after('status'); // 1_day, 5_day
            $table->json('addons')->nullable()->after('kit_type'); // ['lunch_box','wifi','skip_line']
            $table->string('payment_status', 20)->default('pending')->after('addons'); // pending, paid, expired, manual
            $table->string('shopify_order_number')->nullable()->after('payment_status');
            $table->decimal('total_amount', 8, 2)->nullable()->after('shopify_order_number');
            $table->timestamp('paid_at')->nullable()->after('total_amount');
            $table->string('registration_token', 64)->nullable()->after('paid_at');

            $table->unique('shopify_order_number');
            $table->index('registration_token');
            $table->index('payment_status');
        });

        // Mark all legacy rows (created before this column existed) as 'manual'
        // so the expired-cleanup cron does not retire them.
        DB::table('event_media')->update(['payment_status' => 'manual']);
    }

    public function down(): void
    {
        Schema::table('event_media', function (Blueprint $table) {
            $table->dropUnique(['shopify_order_number']);
            $table->dropIndex(['registration_token']);
            $table->dropIndex(['payment_status']);
            $table->dropColumn([
                'kit_type',
                'addons',
                'payment_status',
                'shopify_order_number',
                'total_amount',
                'paid_at',
                'registration_token',
            ]);
        });
    }
};
