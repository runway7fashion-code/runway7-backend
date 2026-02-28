<?php

namespace Database\Seeders;

use App\Models\DesignerCategory;
use App\Models\DesignerProfile;
use App\Models\ModelProfile;
use App\Models\PressProfile;
use App\Models\SponsorProfile;
use App\Models\User;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        // --- Internal Team ---
        User::create([
            'first_name' => 'Admin',
            'last_name'  => 'Runway7',
            'email'      => 'admin@runway7.com',
            'password'   => bcrypt('password123'),
            'role'       => 'admin',
            'status'     => 'active',
        ]);

        User::create([
            'first_name' => 'Maria',
            'last_name'  => 'Gonzalez',
            'email'      => 'accounting@runway7.com',
            'password'   => bcrypt('password123'),
            'role'       => 'accounting',
            'status'     => 'active',
        ]);

        User::create([
            'first_name' => 'Carlos',
            'last_name'  => 'Martinez',
            'email'      => 'operation@runway7.com',
            'password'   => bcrypt('password123'),
            'role'       => 'operation',
            'status'     => 'active',
        ]);

        $salesRep = User::create([
            'first_name' => 'Carlos',
            'last_name'  => 'Mendez',
            'email'      => 'sales@runway7.com',
            'password'   => bcrypt('password123'),
            'role'       => 'sales',
            'status'     => 'active',
        ]);

        // --- Modelos (password: runway7) ---
        // Modelo 1: comp card completo (4 fotos)
        $model1 = User::create([
            'first_name' => 'Sofia',
            'last_name'  => 'Rivera',
            'email'      => 'sofia.rivera@models.com',
            'phone'      => '+1-305-555-0101',
            'password'   => bcrypt('runway7'),
            'role'       => 'model',
            'status'     => 'active',
            'login_code' => 'MOD001',
        ]);

        ModelProfile::create([
            'user_id'            => $model1->id,
            'birth_date'         => '1998-04-15',
            'age'                => 26,
            'gender'             => 'female',
            'location'           => 'Miami, FL',
            'agency'             => 'Elite Model Management',
            'is_agency'          => true,
            'instagram'          => '@sofia.rivera',
            'participation_number' => 1,
            'height'             => 175.00,
            'bust'               => 86.00,
            'waist'              => 61.00,
            'hips'               => 89.00,
            'shoe_size'          => '8',
            'dress_size'         => 'S',
            'ethnicity'          => 'hispanic',
            'hair'               => 'brown',
            'body_type'          => 'slim',
            'photo_1'            => null,
            'photo_2'            => null,
            'photo_3'            => null,
            'photo_4'            => null,
            'compcard_completed' => false,
            'notes'              => 'Experiencia en desfiles internacionales. Disponible para casting en septiembre.',
        ]);

        // Modelo 2: comp card incompleto (2 fotos)
        $model2 = User::create([
            'first_name' => 'Isabella',
            'last_name'  => 'Chen',
            'email'      => 'isabella.chen@models.com',
            'phone'      => '+1-786-555-0202',
            'password'   => bcrypt('runway7'),
            'role'       => 'model',
            'status'     => 'active',
            'login_code' => 'MOD002',
        ]);

        ModelProfile::create([
            'user_id'            => $model2->id,
            'birth_date'         => '2000-09-22',
            'age'                => 24,
            'gender'             => 'female',
            'location'           => 'New York, NY',
            'agency'             => 'IMG Models',
            'is_agency'          => true,
            'instagram'          => '@isabella.chen',
            'participation_number' => 2,
            'height'             => 178.00,
            'bust'               => 84.00,
            'waist'              => 59.00,
            'hips'               => 87.00,
            'shoe_size'          => '9',
            'dress_size'         => 'XS',
            'ethnicity'          => 'asian',
            'hair'               => 'black',
            'body_type'          => 'slim',
            'photo_1'            => null,
            'photo_2'            => null,
            'photo_3'            => null,
            'photo_4'            => null,
            'compcard_completed' => false,
        ]);

        // --- Diseñadores (password: runway7) ---
        $streetwearCat    = DesignerCategory::where('slug', 'streetwear')->first();
        $eveningwearCat   = DesignerCategory::where('slug', 'eveningwear-gowns')->first();

        $designer1 = User::create([
            'first_name' => 'Alejandro',
            'last_name'  => 'Vasquez',
            'email'      => 'ale@nocturnadesign.com',
            'phone'      => '+1-212-555-0303',
            'password'   => bcrypt('runway7'),
            'role'       => 'designer',
            'status'     => 'active',
        ]);

        DesignerProfile::create([
            'user_id'         => $designer1->id,
            'brand_name'      => 'Nocturna Design',
            'collection_name' => 'Dark Elegance SS26',
            'website'         => 'https://nocturnadesign.com',
            'instagram'       => '@nocturnadesign',
            'bio'             => 'Diseñador colombiano radicado en Nueva York. Especializado en alta costura oscura y elegante.',
            'country'         => 'Colombia',
            'category_id'     => $streetwearCat?->id,
            'sales_rep_id'    => $salesRep->id,
            'tracking_link'   => 'https://nocturnadesign.com/tracking/nyfw26',
            'skype'           => 'ale.vasquez.nocturna',
            'social_media'    => [
                'instagram' => '@nocturnadesign',
                'facebook'  => 'nocturnadesign',
                'tiktok'    => '@nocturnadesign',
                'website'   => 'https://nocturnadesign.com',
                'other'     => '',
            ],
        ]);

        $designer2 = User::create([
            'first_name' => 'Valentina',
            'last_name'  => 'Morales',
            'email'      => 'val@lunawhite.com',
            'phone'      => '+1-305-555-0404',
            'password'   => bcrypt('runway7'),
            'role'       => 'designer',
            'status'     => 'active',
        ]);

        DesignerProfile::create([
            'user_id'         => $designer2->id,
            'brand_name'      => 'Luna White',
            'collection_name' => 'Monochrome Dreams',
            'website'         => 'https://lunawhite.com',
            'instagram'       => '@lunawhitestudio',
            'bio'             => 'Diseñadora venezolana. Minimalismo y blanco como filosofía de vida.',
            'country'         => 'Venezuela',
            'category_id'     => $eveningwearCat?->id,
            'tracking_link'   => 'https://lunawhite.com/tracking/nyfw26',
            'skype'           => 'valentina.morales.lw',
            'social_media'    => [
                'instagram' => '@lunawhitestudio',
                'facebook'  => 'lunawhitestudio',
                'tiktok'    => '@lunawhite',
                'website'   => 'https://lunawhite.com',
                'other'     => '',
            ],
        ]);

        // --- Prensa ---
        $press = User::create([
            'first_name' => 'James',
            'last_name'  => 'Walker',
            'email'      => 'james.walker@fashionweekly.com',
            'password'   => bcrypt('password123'),
            'role'       => 'press',
            'status'     => 'active',
        ]);

        PressProfile::create([
            'user_id'      => $press->id,
            'media_outlet' => 'Fashion Weekly',
            'position'     => 'Senior Editor',
            'website'      => 'https://fashionweekly.com',
            'instagram'    => '@jwalker_fashion',
        ]);

        // --- Sponsor ---
        $sponsor = User::create([
            'first_name' => 'Rachel',
            'last_name'  => 'Kim',
            'email'      => 'rachel@luxebrand.com',
            'password'   => bcrypt('password123'),
            'role'       => 'sponsor',
            'status'     => 'active',
        ]);

        SponsorProfile::create([
            'user_id'            => $sponsor->id,
            'company_name'       => 'Luxe Brand Co.',
            'sponsorship_level'  => 'gold',
            'website'            => 'https://luxebrand.com',
            'notes'              => 'Sponsor principal del evento. Presupuesto confirmado.',
        ]);

        // --- Asistentes ---
        User::create([
            'first_name' => 'Emily',
            'last_name'  => 'Johnson',
            'email'      => 'emily.j@gmail.com',
            'phone'      => '+1-305-555-0505',
            'password'   => bcrypt('password123'),
            'role'       => 'attendee',
            'status'     => 'active',
        ]);

        User::create([
            'first_name' => 'Michael',
            'last_name'  => 'Torres',
            'email'      => 'm.torres@gmail.com',
            'password'   => bcrypt('password123'),
            'role'       => 'attendee',
            'status'     => 'active',
        ]);

        User::create([
            'first_name' => 'Victoria',
            'last_name'  => 'Reyes',
            'email'      => 'victoria.r@vip.runway7.com',
            'phone'      => '+1-786-555-0606',
            'password'   => bcrypt('password123'),
            'role'       => 'vip',
            'status'     => 'active',
        ]);
    }
}
