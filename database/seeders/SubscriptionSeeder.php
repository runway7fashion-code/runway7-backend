<?php

namespace Database\Seeders;

use App\Models\Subscription;
use Illuminate\Database\Seeder;

class SubscriptionSeeder extends Seeder
{
    /**
     * Pre-loads the company subscriptions currently active.
     * Amounts and renewal dates are placeholders — admin will adjust them.
     * Uses firstOrCreate by name+vendor so re-running is safe.
     */
    public function run(): void
    {
        $today = now()->toDateString();
        $items = [
            // Web — Hosting & infrastructure
            ['name' => 'AWS',                    'vendor' => 'Amazon Web Services',  'department' => 'web',       'category' => 'hosting',       'billing_cycle' => 'monthly', 'amount' => 0],
            ['name' => 'Hostinger',              'vendor' => 'Hostinger',            'department' => 'web',       'category' => 'hosting',       'billing_cycle' => 'annual',  'amount' => 0, 'plan_tier' => 'Plan 1'],
            ['name' => 'Hostinger (secondary)',  'vendor' => 'Hostinger',            'department' => 'web',       'category' => 'hosting',       'billing_cycle' => 'annual',  'amount' => 0, 'plan_tier' => 'Plan 2'],
            ['name' => 'MongoDB Atlas',          'vendor' => 'MongoDB',              'department' => 'web',       'category' => 'infrastructure','billing_cycle' => 'monthly', 'amount' => 0],

            // Web — Communications
            ['name' => 'Mailgun',                'vendor' => 'Mailgun',              'department' => 'web',       'category' => 'email',         'billing_cycle' => 'monthly', 'amount' => 0],
            ['name' => 'Twilio',                 'vendor' => 'Twilio',               'department' => 'web',       'category' => 'sms',           'billing_cycle' => 'monthly', 'amount' => 0],

            // Web — WordPress plugins
            ['name' => 'Elementor Pro',          'vendor' => 'Elementor',            'department' => 'web',       'category' => 'wordpress',     'billing_cycle' => 'annual',  'amount' => 0],
            ['name' => 'Wordfence',              'vendor' => 'Defiant',              'department' => 'web',       'category' => 'wordpress',     'billing_cycle' => 'annual',  'amount' => 0],
            ['name' => 'FooEvents',              'vendor' => 'FooEvents',            'department' => 'web',       'category' => 'wordpress',     'billing_cycle' => 'annual',  'amount' => 0],
            ['name' => 'DPL Square Integration', 'vendor' => 'DPL',                  'department' => 'web',       'category' => 'wordpress',     'billing_cycle' => 'annual',  'amount' => 0],
            ['name' => 'RankMath',               'vendor' => 'RankMath',             'department' => 'web',       'category' => 'seo',           'billing_cycle' => 'annual',  'amount' => 0],
            ['name' => 'Imagify',                'vendor' => 'WP Media',             'department' => 'web',       'category' => 'wordpress',     'billing_cycle' => 'monthly', 'amount' => 0],
            ['name' => 'WP Rocket',              'vendor' => 'WP Media',             'department' => 'web',       'category' => 'wordpress',     'billing_cycle' => 'annual',  'amount' => 0],
            ['name' => 'Surfer SEO',             'vendor' => 'Surfer',               'department' => 'web',       'category' => 'seo',           'billing_cycle' => 'monthly', 'amount' => 0],

            // Web — E-commerce
            ['name' => 'Shopify',                'vendor' => 'Shopify',              'department' => 'web',       'category' => 'ecommerce',     'billing_cycle' => 'monthly', 'amount' => 0, 'plan_tier' => 'Store 1'],
            ['name' => 'Shopify (secondary)',    'vendor' => 'Shopify',              'department' => 'web',       'category' => 'ecommerce',     'billing_cycle' => 'monthly', 'amount' => 0, 'plan_tier' => 'Store 2'],
            ['name' => 'Square',                 'vendor' => 'Block, Inc.',          'department' => 'web',       'category' => 'ecommerce',     'billing_cycle' => 'monthly', 'amount' => 0],

            // Web — AI
            ['name' => 'Claude',                 'vendor' => 'Anthropic',            'department' => 'web',       'category' => 'ai',            'billing_cycle' => 'monthly', 'amount' => 0],

            // Marketing
            ['name' => 'ChatGPT',                'vendor' => 'OpenAI',               'department' => 'marketing', 'category' => 'ai',            'billing_cycle' => 'monthly', 'amount' => 0],
            ['name' => 'Canva',                  'vendor' => 'Canva',                'department' => 'marketing', 'category' => 'design_tools',  'billing_cycle' => 'annual',  'amount' => 0],
            ['name' => 'Zoho',                   'vendor' => 'Zoho Corporation',     'department' => 'marketing', 'category' => 'productivity',  'billing_cycle' => 'monthly', 'amount' => 0],
        ];

        foreach ($items as $item) {
            Subscription::firstOrCreate(
                [
                    'name' => $item['name'],
                    'vendor' => $item['vendor'],
                ],
                array_merge([
                    'description' => null,
                    'account_email' => null,
                    'auto_renew' => true,
                    'status' => 'active',
                    'purchase_date' => null,
                    'next_renewal_date' => null,
                ], $item)
            );
        }

        $this->command->info('Seeded ' . count($items) . ' subscription placeholders. Admin should edit amounts, dates and cards.');
    }
}
