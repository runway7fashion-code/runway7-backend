<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class ModelsTemplateExport implements FromArray, WithHeadings, WithStyles, ShouldAutoSize
{
    public function headings(): array
    {
        return [
            'email',
            'first_name',
            'last_name',
            'phone',
            'age',
            'gender',
            'city',
            'height',
            'bust',
            'waist',
            'hips',
            'shoe_size',
            'dress_size',
            'ethnicity',
            'hair',
            'body_type',
            'instagram',
            'agency',
            'casting_time',
        ];
    }

    public function array(): array
    {
        return [
            [
                'jane.doe@email.com',
                'Jane',
                'Doe',
                '+1 555-123-4567',
                24,
                'female',
                'New York, NY',
                68.9,
                33.9,
                24,
                35,
                '8',
                'S',
                'caucasian',
                'brown',
                'slim',
                '@jane.doe',
                'IMG Models',
                '09:00',
            ],
            [
                'maria.garcia@email.com',
                'Maria',
                'Garcia',
                '+1 305-987-6543',
                22,
                'female',
                'Miami, FL',
                66.9,
                33.1,
                23.6,
                34.6,
                '7.5',
                'XS',
                'hispanic',
                'black',
                'athletic',
                '@maria.garcia',
                '',
                '10:30',
            ],
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        $lastCol = 'S';

        // Add notes row
        $sheet->insertNewRowBefore(2, 1);
        $notes = [
            'Required',          // email
            'Optional',          // first_name
            'Optional',          // last_name
            'Optional',          // phone
            'Optional',          // age
            'female / male / non_binary',
            'City, State',       // city
            'inches',            // height
            'inches',            // bust
            'inches',            // waist
            'inches',            // hips
            'US size',           // shoe_size
            'XXS/XS/S/M/L/XL/XXL',
            'asian / black / caucasian / hispanic / middle_eastern / mixed / other',
            'black / brown / blonde / red / gray / other',
            'slim / athletic / average / curvy / plus_size',
            '@username',         // instagram
            'Optional',          // agency
            'HH:MM format',      // casting_time
        ];
        foreach ($notes as $i => $note) {
            $col = chr(65 + $i);
            $sheet->setCellValue("{$col}2", $note);
        }

        return [
            1 => [
                'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '000000']],
            ],
            2 => [
                'font' => ['italic' => true, 'size' => 9, 'color' => ['rgb' => '888888']],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'F5F5F5']],
            ],
        ];
    }
}
