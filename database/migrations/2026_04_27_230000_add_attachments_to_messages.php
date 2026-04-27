<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            $table->string('attachment_url')->nullable()->after('image_url');
            $table->string('attachment_mime', 120)->nullable()->after('attachment_url');
            $table->unsignedBigInteger('attachment_size')->nullable()->after('attachment_mime'); // bytes
            $table->unsignedInteger('attachment_duration')->nullable()->after('attachment_size'); // seconds (audio/video)
            $table->string('attachment_name')->nullable()->after('attachment_duration'); // original filename
        });

        // Extend the type CHECK constraint to allow audio + document.
        DB::statement('ALTER TABLE messages DROP CONSTRAINT IF EXISTS messages_type_check');
        DB::statement("ALTER TABLE messages ADD CONSTRAINT messages_type_check
            CHECK (type::text = ANY (ARRAY['text'::varchar, 'image'::varchar, 'audio'::varchar, 'document'::varchar, 'system'::varchar]::text[]))");
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE messages DROP CONSTRAINT IF EXISTS messages_type_check');
        DB::statement("ALTER TABLE messages ADD CONSTRAINT messages_type_check
            CHECK (type::text = ANY (ARRAY['text'::varchar, 'image'::varchar, 'system'::varchar]::text[]))");

        Schema::table('messages', function (Blueprint $table) {
            $table->dropColumn([
                'attachment_url', 'attachment_mime', 'attachment_size',
                'attachment_duration', 'attachment_name',
            ]);
        });
    }
};
