<?php

namespace Database\Seeders;

use App\Models\Sponsorship\Category;
use Illuminate\Database\Seeder;

class SponsorshipCategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            'Financial',
            'Government Institution',
            'Law Firms',
            'Edutainment',
            'Transportation',
            'Fitness',
            'Petcare',
            'Retail',
            'Mobile Services',
            'Automobiles',
            'Toys',
            'Casinos and Betting Centers',
            'Gold Buyers',
            'Storage Places',
            'Tourism',
            'Beauty',
            'Clinical',
            'Entertainment',
            'Health Care / Wellness',
            'Home & Living',
            'Politics',
            'Startups',
            'Apps',
            'Accessories',
            'Tech',
            'Marketing Agencies',
            'Beverage',
            'Alternative Energy',
        ];

        foreach ($categories as $name) {
            Category::firstOrCreate(['name' => $name], ['is_active' => true]);
        }
    }
}
