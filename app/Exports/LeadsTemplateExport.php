<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class LeadsTemplateExport implements FromArray, WithHeadings, WithStyles, ShouldAutoSize
{
    public function headings(): array
    {
        return [
            'email',
            'first_name',
            'last_name',
            'phone',
            'country',
            'company_name',
            'retail_category',
            'website_url',
            'instagram',
            'budget',
            'past_shows',
            'notes',
        ];
    }

    public function array(): array
    {
        return [
            [
                'designer@example.com',
                'John',
                'Smith',
                '+1 555-123-4567',
                'United States',
                'Smith Fashion House',
                'Luxury',
                'https://smithfashion.com',
                '@smithfashion',
                '$5,000 - $10,000',
                'Yes',
                'Interested in Fall 2026 show',
            ],
            [
                'maria@moda.com',
                'Maria',
                'Lopez',
                '+52 55-9876-5432',
                'Mexico',
                'Moda MX',
                'Streetwear',
                'https://modamx.com',
                '@modamx',
                '$3,000 - $5,000',
                'No',
                '',
            ],
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        $lastCol = 'L';

        $sheet->insertNewRowBefore(2, 1);
        $notes = [
            'Required',        // email
            'Optional',        // first_name
            'Optional',        // last_name
            'Optional',        // phone
            'Optional',        // country
            'Optional',        // company_name
            'Optional',        // retail_category
            'Optional (URL)',  // website_url
            '@username',       // instagram
            'Optional',        // budget
            'Yes / No',        // past_shows
            'Optional',        // notes
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
