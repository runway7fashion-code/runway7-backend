<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Convert height, bust, waist, hips from cm to inches.
     */
    public function up(): void
    {
        DB::table('model_profiles')
            ->whereNotNull('height')
            ->update(['height' => DB::raw('ROUND(height / 2.54, 2)')]);

        DB::table('model_profiles')
            ->whereNotNull('bust')
            ->update(['bust' => DB::raw('ROUND(bust / 2.54, 2)')]);

        DB::table('model_profiles')
            ->whereNotNull('waist')
            ->update(['waist' => DB::raw('ROUND(waist / 2.54, 2)')]);

        DB::table('model_profiles')
            ->whereNotNull('hips')
            ->update(['hips' => DB::raw('ROUND(hips / 2.54, 2)')]);
    }

    /**
     * Convert back from inches to cm.
     */
    public function down(): void
    {
        DB::table('model_profiles')
            ->whereNotNull('height')
            ->update(['height' => DB::raw('ROUND(height * 2.54, 2)')]);

        DB::table('model_profiles')
            ->whereNotNull('bust')
            ->update(['bust' => DB::raw('ROUND(bust * 2.54, 2)')]);

        DB::table('model_profiles')
            ->whereNotNull('waist')
            ->update(['waist' => DB::raw('ROUND(waist * 2.54, 2)')]);

        DB::table('model_profiles')
            ->whereNotNull('hips')
            ->update(['hips' => DB::raw('ROUND(hips * 2.54, 2)')]);
    }
};
