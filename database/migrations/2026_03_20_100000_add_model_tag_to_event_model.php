<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE event_model ADD COLUMN model_tag VARCHAR(20) DEFAULT NULL");
        DB::statement("ALTER TABLE event_model ADD CONSTRAINT event_model_model_tag_check CHECK (model_tag IS NULL OR model_tag::text = ANY (ARRAY['runway_merch', 'runway_brand']))");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE event_model DROP CONSTRAINT IF EXISTS event_model_model_tag_check");
        DB::statement("ALTER TABLE event_model DROP COLUMN IF EXISTS model_tag");
    }
};
