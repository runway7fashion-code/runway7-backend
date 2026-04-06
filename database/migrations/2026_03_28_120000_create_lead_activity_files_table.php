<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lead_activity_files', function (Blueprint $table) {
            $table->id();
            $table->foreignId('activity_id')->constrained('lead_activities')->cascadeOnDelete();
            $table->string('file_path');
            $table->string('file_name');
            $table->timestamps();
        });

        // Migrate existing file_path/file_name from lead_activities
        $activities = DB::table('lead_activities')
            ->whereNotNull('file_path')
            ->where('file_path', '!=', '')
            ->get(['id', 'file_path', 'file_name', 'created_at']);

        foreach ($activities as $a) {
            DB::table('lead_activity_files')->insert([
                'activity_id' => $a->id,
                'file_path'   => $a->file_path,
                'file_name'   => $a->file_name,
                'created_at'  => $a->created_at,
                'updated_at'  => $a->created_at,
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('lead_activity_files');
    }
};
