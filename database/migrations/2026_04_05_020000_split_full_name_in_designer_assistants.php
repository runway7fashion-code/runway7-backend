<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('designer_assistants', function (Blueprint $table) {
            $table->string('first_name')->nullable()->after('event_id');
            $table->string('last_name')->nullable()->after('first_name');
        });

        // Migrate existing data: split full_name into first_name + last_name
        DB::table('designer_assistants')->whereNotNull('full_name')->orderBy('id')->each(function ($row) {
            $parts = explode(' ', trim($row->full_name), 2);
            DB::table('designer_assistants')->where('id', $row->id)->update([
                'first_name' => $parts[0],
                'last_name' => $parts[1] ?? '',
            ]);
        });

        Schema::table('designer_assistants', function (Blueprint $table) {
            $table->dropColumn('full_name');
        });
    }

    public function down(): void
    {
        Schema::table('designer_assistants', function (Blueprint $table) {
            $table->string('full_name')->nullable()->after('event_id');
        });

        DB::table('designer_assistants')->orderBy('id')->each(function ($row) {
            DB::table('designer_assistants')->where('id', $row->id)->update([
                'full_name' => trim(($row->first_name ?? '') . ' ' . ($row->last_name ?? '')),
            ]);
        });

        Schema::table('designer_assistants', function (Blueprint $table) {
            $table->dropColumn(['first_name', 'last_name']);
        });
    }
};
