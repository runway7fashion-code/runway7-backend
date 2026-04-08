<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('countries', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code', 5)->unique();
            $table->string('phone', 10);
            $table->string('flag', 10)->nullable();
            $table->integer('order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // Seed from config
        $countries = config('phone_codes');
        if ($countries) {
            foreach ($countries as $i => $c) {
                \DB::table('countries')->insert([
                    'name'       => $c['name'],
                    'code'       => $c['code'],
                    'phone'      => $c['phone'],
                    'flag'       => $c['flag'] ?? null,
                    'order'      => $i,
                    'is_active'  => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('countries');
    }
};
