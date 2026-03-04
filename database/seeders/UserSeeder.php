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

        // Modelo 3
        $model3 = User::create([
            'first_name' => 'Camila',
            'last_name'  => 'Duarte',
            'email'      => 'camila.duarte@models.com',
            'phone'      => '+1-212-555-0133',
            'password'   => bcrypt('runway7'),
            'role'       => 'model',
            'status'     => 'pending',
            'login_code' => 'MOD003',
        ]);

        ModelProfile::create([
            'user_id'            => $model3->id,
            'birth_date'         => '1999-07-10',
            'age'                => 26,
            'gender'             => 'female',
            'location'           => 'Los Angeles, CA',
            'agency'             => 'Wilhelmina Models',
            'is_agency'          => true,
            'instagram'          => '@camila.duarte',
            'participation_number' => 3,
            'height'             => 177.00,
            'bust'               => 85.00,
            'waist'              => 60.00,
            'hips'               => 88.00,
            'shoe_size'          => '8.5',
            'dress_size'         => 'S',
            'ethnicity'          => 'hispanic',
            'hair'               => 'brown',
            'body_type'          => 'slim',
            'compcard_completed' => false,
        ]);

        // Modelo 4
        $model4 = User::create([
            'first_name' => 'Aisha',
            'last_name'  => 'Williams',
            'email'      => 'aisha.w@models.com',
            'phone'      => '+1-646-555-0144',
            'password'   => bcrypt('runway7'),
            'role'       => 'model',
            'status'     => 'pending',
            'login_code' => 'MOD004',
        ]);

        ModelProfile::create([
            'user_id'            => $model4->id,
            'birth_date'         => '2001-01-28',
            'age'                => 25,
            'gender'             => 'female',
            'location'           => 'Brooklyn, NY',
            'agency'             => 'Next Management',
            'is_agency'          => true,
            'instagram'          => '@aisha.w',
            'participation_number' => 4,
            'height'             => 180.00,
            'bust'               => 82.00,
            'waist'              => 58.00,
            'hips'               => 86.00,
            'shoe_size'          => '9',
            'dress_size'         => 'XS',
            'ethnicity'          => 'black',
            'hair'               => 'black',
            'body_type'          => 'slim',
            'compcard_completed' => false,
        ]);

        // Modelo 5
        $model5 = User::create([
            'first_name' => 'Natalia',
            'last_name'  => 'Petrov',
            'email'      => 'natalia.p@models.com',
            'phone'      => '+1-917-555-0155',
            'password'   => bcrypt('runway7'),
            'role'       => 'model',
            'status'     => 'pending',
            'login_code' => 'MOD005',
        ]);

        ModelProfile::create([
            'user_id'            => $model5->id,
            'birth_date'         => '2000-11-05',
            'age'                => 25,
            'gender'             => 'female',
            'location'           => 'Manhattan, NY',
            'agency'             => 'Ford Models',
            'is_agency'          => true,
            'instagram'          => '@natalia.petrov',
            'participation_number' => 5,
            'height'             => 176.00,
            'bust'               => 83.00,
            'waist'              => 60.00,
            'hips'               => 88.00,
            'shoe_size'          => '8',
            'dress_size'         => 'S',
            'ethnicity'          => 'caucasian',
            'hair'               => 'blonde',
            'body_type'          => 'slim',
            'compcard_completed' => false,
        ]);

        // --- Modelos adicionales 6–35 (password: runway7) ---
        // [first, last, email, phone, code, status, birth, age, gender, loc, agency, insta, p#, h, bust, waist, hips, shoe, dress, ethn, hair, body]
        $extraModels = [
            ['Daniela', 'Ortiz', 'daniela.ortiz@models.com', '+1-305-555-0601', 'MOD006', 'active', '1999-03-12', 26, 'female', 'Miami, FL', 'Ford Models', '@daniela.ortiz', 6, 174, 85, 60, 88, '8', 'S', 'hispanic', 'brown', 'slim'],
            ['Marco', 'Rivera', 'marco.rivera@models.com', '+1-212-555-0602', 'MOD007', 'active', '2000-06-18', 25, 'male', 'New York, NY', 'IMG Models', '@marco.rivera', 7, 188, 98, 80, 93, '11', 'M', 'hispanic', 'black', 'athletic'],
            ['Lina', 'Park', 'lina.park@models.com', '+1-646-555-0603', 'MOD008', 'active', '2001-02-25', 25, 'female', 'Manhattan, NY', 'Elite Model Management', '@lina.park', 8, 176, 82, 58, 86, '8', 'XS', 'asian', 'black', 'slim'],
            ['Emma', 'Thompson', 'emma.thompson@models.com', '+1-310-555-0604', 'MOD009', 'active', '1998-11-07', 27, 'female', 'Los Angeles, CA', 'Next Management', '@emma.thompson', 9, 178, 84, 60, 88, '9', 'S', 'caucasian', 'blonde', 'slim'],
            ['Valentina', 'Cruz', 'valentina.cruz@models.com', '+1-786-555-0605', 'MOD010', 'active', '2000-01-20', 26, 'female', 'Miami, FL', 'Wilhelmina Models', '@valentina.cruz', 10, 175, 86, 61, 89, '8', 'S', 'hispanic', 'brown', 'slim'],
            ['Zara', 'Ahmed', 'zara.ahmed@models.com', '+1-917-555-0606', 'MOD011', 'active', '1999-08-14', 26, 'female', 'Brooklyn, NY', 'Storm Models', '@zara.ahmed', 11, 177, 83, 59, 87, '8.5', 'XS', 'middle_eastern', 'black', 'slim'],
            ['Mia', 'Santos', 'mia.santos@models.com', '+1-305-555-0607', 'MOD012', 'active', '2001-05-30', 24, 'female', 'Miami, FL', 'DNA Model Management', '@mia.santos', 12, 173, 85, 61, 89, '7.5', 'S', 'hispanic', 'brown', 'athletic'],
            ['Yuki', 'Tanaka', 'yuki.tanaka@models.com', '+1-415-555-0608', 'MOD013', 'pending', '2000-12-03', 25, 'female', 'San Francisco, CA', 'Ford Models', '@yuki.tanaka', 13, 172, 80, 57, 85, '7', 'XS', 'asian', 'black', 'slim'],
            ['Olivia', 'White', 'olivia.white@models.com', '+1-323-555-0609', 'MOD014', 'active', '1999-07-22', 26, 'female', 'Los Angeles, CA', 'The Society Management', '@olivia.white', 14, 180, 84, 60, 88, '9', 'S', 'caucasian', 'red', 'slim'],
            ['Amara', 'Diallo', 'amara.diallo@models.com', '+1-347-555-0610', 'MOD015', 'active', '2001-09-15', 24, 'female', 'Brooklyn, NY', 'IMG Models', '@amara.diallo', 15, 181, 82, 58, 86, '9.5', 'XS', 'black', 'black', 'slim'],
            ['Andre', 'Baptiste', 'andre.baptiste@models.com', '+1-786-555-0611', 'MOD016', 'active', '2000-04-08', 25, 'male', 'Miami, FL', 'Elite Model Management', '@andre.baptiste', 16, 190, 100, 82, 95, '11.5', 'L', 'black', 'black', 'athletic'],
            ['Chloe', 'Martin', 'chloe.martin@models.com', '+1-212-555-0612', 'MOD017', 'active', '1998-10-19', 27, 'female', 'Manhattan, NY', 'Women Management', '@chloe.martin', 17, 179, 83, 59, 87, '9', 'XS', 'caucasian', 'blonde', 'slim'],
            ['Priya', 'Sharma', 'priya.sharma@models.com', '+1-718-555-0613', 'MOD018', 'pending', '2001-01-11', 25, 'female', 'Queens, NY', 'Heroes Model Management', '@priya.sharma', 18, 174, 82, 58, 86, '7.5', 'XS', 'asian', 'black', 'slim'],
            ['Adriana', 'Lopez', 'adriana.lopez@models.com', '+1-305-555-0614', 'MOD019', 'active', '1999-06-25', 26, 'female', 'Miami, FL', 'Ford Models', '@adriana.lopez', 19, 175, 86, 61, 89, '8', 'S', 'hispanic', 'brown', 'athletic'],
            ['Naomi', 'Scott', 'naomi.scott@models.com', '+1-646-555-0615', 'MOD020', 'active', '2000-03-17', 25, 'female', 'New York, NY', 'DNA Model Management', '@naomi.scott', 20, 178, 84, 60, 88, '8.5', 'S', 'mixed', 'brown', 'slim'],
            ['Sara', 'Kim', 'sara.kim@models.com', '+1-917-555-0616', 'MOD021', 'active', '2001-07-04', 24, 'female', 'Manhattan, NY', 'IMG Models', '@sara.kim', 21, 173, 81, 57, 85, '7.5', 'XS', 'asian', 'black', 'slim'],
            ['Bianca', 'Rossi', 'bianca.rossi@models.com', '+1-212-555-0617', 'MOD022', 'active', '1999-11-28', 26, 'female', 'New York, NY', 'Next Management', '@bianca.rossi', 22, 177, 84, 60, 88, '8.5', 'S', 'caucasian', 'brown', 'slim'],
            ['Fatima', 'Hassan', 'fatima.hassan@models.com', '+1-347-555-0618', 'MOD023', 'pending', '2000-08-09', 25, 'female', 'Brooklyn, NY', 'Storm Models', '@fatima.hassan', 23, 176, 83, 59, 87, '8', 'XS', 'middle_eastern', 'black', 'slim'],
            ['Kai', 'Nakamura', 'kai.nakamura@models.com', '+1-323-555-0619', 'MOD024', 'active', '2001-04-16', 24, 'male', 'Los Angeles, CA', 'Wilhelmina Models', '@kai.nakamura', 24, 185, 96, 78, 91, '10.5', 'M', 'asian', 'black', 'athletic'],
            ['Elena', 'Volkov', 'elena.volkov@models.com', '+1-646-555-0620', 'MOD025', 'active', '1998-12-01', 27, 'female', 'Manhattan, NY', 'The Society Management', '@elena.volkov', 25, 180, 83, 58, 86, '9', 'XS', 'caucasian', 'blonde', 'slim'],
            ['Catalina', 'Reyes', 'catalina.reyes@models.com', '+1-786-555-0621', 'MOD026', 'active', '2000-05-14', 25, 'female', 'Miami, FL', 'Elite Model Management', '@catalina.reyes', 26, 174, 85, 61, 89, '8', 'S', 'hispanic', 'brown', 'slim'],
            ['Nia', 'Brooks', 'nia.brooks@models.com', '+1-718-555-0622', 'MOD027', 'active', '2001-10-23', 24, 'female', 'Brooklyn, NY', 'DNA Model Management', '@nia.brooks', 27, 179, 82, 58, 86, '9', 'XS', 'black', 'black', 'slim'],
            ['Mei', 'Lin', 'mei.lin@models.com', '+1-415-555-0623', 'MOD028', 'pending', '2000-02-07', 26, 'female', 'San Francisco, CA', 'IMG Models', '@mei.lin', 28, 175, 81, 57, 85, '7.5', 'XS', 'asian', 'black', 'slim'],
            ['Jordan', 'Ellis', 'jordan.ellis@models.com', '+1-212-555-0624', 'MOD029', 'active', '1999-09-30', 26, 'male', 'New York, NY', 'Ford Models', '@jordan.ellis', 29, 187, 97, 79, 92, '11', 'M', 'mixed', 'brown', 'athletic'],
            ['Gabriela', 'Mendez', 'gabriela.mendez@models.com', '+1-305-555-0625', 'MOD030', 'active', '2001-06-12', 24, 'female', 'Miami, FL', 'Wilhelmina Models', '@gabriela.mendez', 30, 173, 86, 62, 90, '8', 'S', 'hispanic', 'brown', 'athletic'],
            ['Sienna', 'James', 'sienna.james@models.com', '+1-646-555-0626', 'MOD031', 'active', '2000-11-19', 25, 'female', 'New York, NY', 'Women Management', '@sienna.james', 31, 177, 83, 59, 87, '8.5', 'S', 'mixed', 'brown', 'slim'],
            ['Leila', 'Nazari', 'leila.nazari@models.com', '+1-917-555-0627', 'MOD032', 'pending', '1999-04-05', 26, 'female', 'Manhattan, NY', 'Heroes Model Management', '@leila.nazari', 32, 176, 82, 58, 86, '8', 'XS', 'middle_eastern', 'brown', 'slim'],
            ['Harper', 'Davis', 'harper.davis@models.com', '+1-310-555-0628', 'MOD033', 'active', '2001-08-21', 24, 'female', 'Los Angeles, CA', 'Next Management', '@harper.davis', 33, 178, 84, 60, 88, '8.5', 'S', 'caucasian', 'red', 'slim'],
            ['Xiomara', 'Vega', 'xiomara.vega@models.com', '+1-786-555-0629', 'MOD034', 'active', '2000-07-03', 25, 'female', 'Miami, FL', 'Storm Models', '@xiomara.vega', 34, 175, 85, 61, 89, '8', 'S', 'hispanic', 'black', 'slim'],
            ['Tiana', 'Moore', 'tiana.moore@models.com', '+1-347-555-0630', 'MOD035', 'active', '2001-03-26', 24, 'female', 'Brooklyn, NY', 'Elite Model Management', '@tiana.moore', 35, 180, 83, 59, 87, '9', 'S', 'black', 'black', 'athletic'],
        ];

        foreach ($extraModels as $m) {
            $user = User::create([
                'first_name' => $m[0],
                'last_name'  => $m[1],
                'email'      => $m[2],
                'phone'      => $m[3],
                'password'   => bcrypt('runway7'),
                'role'       => 'model',
                'status'     => $m[5],
                'login_code' => $m[4],
            ]);

            ModelProfile::create([
                'user_id'              => $user->id,
                'birth_date'           => $m[6],
                'age'                  => $m[7],
                'gender'               => $m[8],
                'location'             => $m[9],
                'agency'               => $m[10],
                'is_agency'            => true,
                'instagram'            => $m[11],
                'participation_number' => $m[12],
                'height'               => $m[13],
                'bust'                 => $m[14],
                'waist'                => $m[15],
                'hips'                 => $m[16],
                'shoe_size'            => $m[17],
                'dress_size'           => $m[18],
                'ethnicity'            => $m[19],
                'hair'                 => $m[20],
                'body_type'            => $m[21],
                'compcard_completed'   => false,
            ]);
        }

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
