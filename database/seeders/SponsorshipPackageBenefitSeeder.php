<?php

namespace Database\Seeders;

use App\Models\Sponsorship\PackageBenefit;
use Illuminate\Database\Seeder;

class SponsorshipPackageBenefitSeeder extends Seeder
{
    public function run(): void
    {
        $benefits = [
            'Activation',
            'Email Marketing',
            'Naming Rights',
            'Video Commercial',
            'Video Commercial Accelevents',
            'Live Commercial',
            'Gift Bag Inclusion',
            'Digital Logo Placement',
            'On-site Logo Placement',
            'Social Media Promotion',
            'Press Release Inclusion',
            'Cross-promotion Program',
            'Social Media Thank You Post',
            'Magazine Inclusion',
        ];

        foreach ($benefits as $name) {
            PackageBenefit::firstOrCreate(['name' => $name], ['is_active' => true]);
        }
    }
}
