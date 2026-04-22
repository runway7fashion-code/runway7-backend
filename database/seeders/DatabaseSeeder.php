<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $this->call([
            DesignerSettingsSeeder::class,
            UserSeeder::class,
            EventSeeder::class,
            AccountingSeeder::class,
            SupportCaseSeeder::class,
            ConversationSeeder::class,
            BannerSeeder::class,
            SponsorshipCategorySeeder::class,
            SponsorshipPackageBenefitSeeder::class,
        ]);
    }
}
