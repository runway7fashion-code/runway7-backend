<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('event_designer', function (Blueprint $table) {
            $table->foreignId('package_id')->nullable()->after('status')
                ->constrained('designer_packages')->nullOnDelete();
            $table->integer('looks')->default(10)->after('package_id');
            $table->boolean('model_casting_enabled')->default(true)->after('looks');
            $table->decimal('package_price', 10, 2)->default(0)->after('model_casting_enabled');
            $table->text('notes')->nullable()->after('package_price');
        });
    }

    public function down(): void
    {
        Schema::table('event_designer', function (Blueprint $table) {
            $table->dropConstrainedForeignId('package_id');
            $table->dropColumn(['looks', 'model_casting_enabled', 'package_price', 'notes']);
        });
    }
};
