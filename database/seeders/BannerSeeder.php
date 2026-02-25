<?php

namespace Database\Seeders;

use App\Models\Banner;
use App\Models\Event;
use Illuminate\Database\Seeder;

class BannerSeeder extends Seeder
{
    public function run(): void
    {
        $event = Event::first();

        Banner::create([
            'title'        => 'NYFW September 2026',
            'image_url'    => 'banners/placeholder-nyfw.jpg',
            'link_url'     => 'https://runway7.com',
            'target_roles' => null,
            'event_id'     => $event?->id,
            'order'        => 1,
            'status'       => 'active',
        ]);

        Banner::create([
            'title'        => 'Designer Registration',
            'image_url'    => 'banners/placeholder-designer.jpg',
            'link_url'     => 'https://runway7.com/designers',
            'target_roles' => ['designer'],
            'order'        => 2,
            'status'       => 'active',
        ]);

        Banner::create([
            'title'        => 'Model Casting Info',
            'image_url'    => 'banners/placeholder-casting.jpg',
            'link_url'     => 'https://runway7.com/casting',
            'target_roles' => ['model'],
            'order'        => 3,
            'status'       => 'active',
        ]);
    }
}
