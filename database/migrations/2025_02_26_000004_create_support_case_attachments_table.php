<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('support_case_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('support_case_message_id')->constrained('support_case_messages')->cascadeOnDelete();
            $table->string('file_url');
            $table->string('file_name');
            $table->string('file_type');
            $table->integer('file_size')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('support_case_attachments');
    }
};
