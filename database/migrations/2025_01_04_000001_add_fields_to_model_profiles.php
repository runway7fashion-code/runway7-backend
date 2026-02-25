<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('model_profiles', function (Blueprint $table) {
            $table->enum('gender', ['female', 'male', 'non_binary'])->default('female')->after('age');
            $table->string('location')->nullable()->after('gender');
            $table->string('chest')->nullable()->after('bust');
            $table->enum('body_type', ['slim', 'athletic', 'average', 'curvy', 'plus_size'])->nullable()->after('shoe_size');
            $table->enum('ethnicity', ['asian', 'black', 'caucasian', 'hispanic', 'middle_eastern', 'mixed', 'other'])->nullable()->after('body_type');
            $table->enum('hair', ['black', 'brown', 'blonde', 'red', 'gray', 'other'])->nullable()->after('ethnicity');
            $table->boolean('is_agency')->default(false)->after('agency');
            $table->boolean('is_test_model')->default(false)->after('is_agency');
            $table->string('photo_1')->nullable()->after('photos');
            $table->string('photo_2')->nullable()->after('photo_1');
            $table->string('photo_3')->nullable()->after('photo_2');
            $table->string('photo_4')->nullable()->after('photo_3');
            $table->boolean('compcard_completed')->default(false)->after('photo_4');
        });
    }

    public function down(): void
    {
        Schema::table('model_profiles', function (Blueprint $table) {
            $table->dropColumn([
                'gender', 'location', 'chest', 'body_type', 'ethnicity',
                'hair', 'is_agency', 'is_test_model',
                'photo_1', 'photo_2', 'photo_3', 'photo_4', 'compcard_completed',
            ]);
        });
    }
};
