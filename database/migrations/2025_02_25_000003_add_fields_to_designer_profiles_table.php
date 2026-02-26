<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('designer_profiles', function (Blueprint $table) {
            $table->foreignId('category_id')->nullable()->after('user_id')
                ->constrained('designer_categories')->nullOnDelete();
            $table->string('tracking_link')->nullable()->after('website');
            $table->string('skype')->nullable()->after('instagram');
            $table->json('social_media')->nullable()->after('skype');
        });
    }

    public function down(): void
    {
        Schema::table('designer_profiles', function (Blueprint $table) {
            $table->dropConstrainedForeignId('category_id');
            $table->dropColumn(['tracking_link', 'skype', 'social_media']);
        });
    }
};
