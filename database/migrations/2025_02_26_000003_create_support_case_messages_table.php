<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('support_case_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('support_case_id')->constrained('support_cases')->cascadeOnDelete();
            $table->enum('sender_type', ['designer', 'team']);
            $table->foreignId('team_member_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('message');
            $table->date('message_date');
            $table->timestamps();

            $table->index(['support_case_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('support_case_messages');
    }
};
