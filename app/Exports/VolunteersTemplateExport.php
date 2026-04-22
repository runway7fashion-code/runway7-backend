<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class VolunteersTemplateExport implements FromArray, WithHeadings, WithStyles, ShouldAutoSize
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
            'instagram',
            'location',
            'tshirt_size',
            'experience',
            'work_style',
            'availability',
            'contribution',
            'resume_link',
            'notes',
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
                '@jane.doe',
                'New York, NY',
                'M',
                'I have volunteered at 3 fashion events and managed backstage teams.',
                'yes',
                'full',
                'I can help with logistics and backstage coordination.',
                'https://drive.google.com/file/d/123abc/view',
                'Prefers evening shifts. Speaks English and Spanish.',
            ],
            [
                'maria.garcia@email.com',
                'Maria',
                'Garcia',
                '+1 305-987-6543',
                22,
                'female',
                '@maria.garcia',
                'Miami, FL',
                'S',
                'First time volunteering but bilingual and organized.',
                'yes',
                'partial',
                'Available for model check-in and guest reception.',
                '',
                '',
            ],
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        $sheet->insertNewRowBefore(2, 1);
        $notes = [
            'Required',                                  // email
            'Optional',                                  // first_name
            'Optional',                                  // last_name
            'Optional',                                  // phone
            'Optional (number)',                         // age
            'female / male / non_binary',                // gender
            '@username',                                 // instagram
            'City, State',                               // location
            'XS / S / M / L / XL / XXL',                 // tshirt_size
            'Free text',                                 // experience
            'yes / no (comfortable in fast-paced env)',  // work_style
            'full / partial / limited',                  // availability
            'Free text',                                 // contribution
            'Public URL (Google Drive, Dropbox, etc.)',  // resume_link
            'Internal notes (free text)',                // notes
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
