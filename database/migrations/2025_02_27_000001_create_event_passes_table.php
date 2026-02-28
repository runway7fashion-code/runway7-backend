<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('event_passes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained('events')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('issued_by')->nullable()->constrained('users')->nullOnDelete();

            $table->string('qr_code')->unique();  // e.g. PASS-A3F9X2
            $table->string('pass_type');           // model, designer, staff, media, volunteer, vip, press, sponsor, complementary, guest
            $table->string('holder_name');         // Nombre en el pase (puede diferir del user)
            $table->string('holder_email')->nullable();

            $table->json('valid_days')->nullable(); // array de event_day_ids, null = todos los días
            $table->string('status')->default('active'); // active, used, cancelled

            $table->timestamp('checked_in_at')->nullable();
            $table->json('check_in_history')->nullable(); // [{day_id, checked_in_at, checked_by}]

            $table->text('notes')->nullable();
            $table->timestamp('issued_at')->useCurrent();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_passes');
    }
};
