<?php

namespace Database\Seeders;

use App\Models\DesignerCategory;
use App\Models\DesignerPackage;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class DesignerSettingsSeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            'Athleisure', 'Accessories', 'Activewear-Sportswear', 'Bridal',
            'Eveningwear-Gowns', 'Indigenous', 'Kids-Youth', 'Lingerie',
            'Resort-Swimwear', 'Streetwear', 'Suits', 'Upcycle-Organic',
            'Ready to Wear', 'Other',
        ];

        foreach ($categories as $i => $name) {
            DesignerCategory::create([
                'name'  => $name,
                'slug'  => Str::slug($name),
                'order' => $i + 1,
            ]);
        }

        $packages = [
            ['name' => 'Emerging',     'price' => 2500,  'looks' => 8,  'assistants' => 2],
            ['name' => 'Emerging Plus', 'price' => 3500,  'looks' => 10, 'assistants' => 3],
            ['name' => 'Premium',       'price' => 5000,  'looks' => 12, 'assistants' => 4],
            ['name' => 'Platinum',      'price' => 7500,  'looks' => 15, 'assistants' => 5],
            ['name' => 'Diamond',       'price' => 10000, 'looks' => 20, 'assistants' => 8],
            ['name' => 'Private Hour',  'price' => 15000, 'looks' => 30, 'assistants' => 10],
        ];

        foreach ($packages as $i => $pkg) {
            DesignerPackage::create([
                'name'               => $pkg['name'],
                'slug'               => Str::slug($pkg['name']),
                'price'              => $pkg['price'],
                'default_looks'      => $pkg['looks'],
                'default_assistants' => $pkg['assistants'],
                'order'              => $i + 1,
            ]);
        }
    }
}
