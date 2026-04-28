<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('material_instructions', function (Blueprint $table) {
            $table->id();
            $table->string('material_name', 100)->unique();
            $table->text('instructions')->nullable();
            $table->timestamps();
        });

        // Seed: pre-populate with the 10 known materials (8 with text, 2 empty)
        $now = now();
        $rows = [
            [
                'material_name' => 'Background',
                'instructions'  => "Background Video References (Exclusively for Premium, Platinum, and Diamond packages, as well as Private Shows)\n\nPlease submit concepts or references, and our production team will develop the background based on your vision. Include details such as keywords, colors, shapes, textures, models, and overall mood. You may also share links to reels, campaigns, images, or mood boards for inspiration.\n\nAlternatively, you may submit a finalized background video following the official specifications provided in the folder (please contact us in advance to confirm the duration).\n\nThis feature is not included in Emerging / Emerging+ packages. For those packages, your runway will feature a standard white background with your logo, as outlined in the designer deck and your signed contract, unless it has been purchased as an add-on. If you would like to upgrade, please contact us.",
            ],
            [
                'material_name' => 'Music',
                'instructions'  => "You may submit 1 to 3 songs (in MP3 format). Our team will create a professionally mixed final soundtrack based on your selections.\n\nIf you prefer to submit a fully edited soundtrack, please contact us in advance to confirm the exact duration of your runway presentation.",
            ],
            [
                'material_name' => 'Images',
                'instructions'  => 'A minimum of 15 high-quality images for promotional use across social media.',
            ],
            [
                'material_name' => 'Runway Logo',
                'instructions'  => null,
            ],
            [
                'material_name' => 'Bio',
                'instructions'  => 'This will support our marketing team in effectively communicating your brand story across promotions and press, as well as when sharing your brand with media outlets.',
            ],
            [
                'material_name' => 'Hair Mood Board',
                'instructions'  => 'To define the hairstyle direction for your models. You may select from the options provided in the portal or submit your own reference/inspiration images.',
            ],
            [
                'material_name' => 'Makeup Mood Board',
                'instructions'  => 'To define the makeup direction for your models (women and/or men). You may select from our options or provide your own references.',
            ],
            [
                'material_name' => 'Brand Logo',
                'instructions'  => 'For all branding purposes, including promotional materials, visuals, background video, and magazine features.',
            ],
            [
                'material_name' => 'Designer Photo',
                'instructions'  => 'For any possible media features.',
            ],
            [
                'material_name' => 'Artworks',
                'instructions'  => null,
            ],
        ];

        foreach ($rows as &$row) {
            $row['created_at'] = $now;
            $row['updated_at'] = $now;
        }
        DB::table('material_instructions')->insert($rows);
    }

    public function down(): void
    {
        Schema::dropIfExists('material_instructions');
    }
};
