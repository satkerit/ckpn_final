<?php

declare(strict_types=1);

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Template Excel kosong untuk upload data pembiayaan.
 * Heading sesuai kolom yang diharapkan importer.
 * Ref: PRD Bab 15
 */
class FinancingUploadTemplateExport implements FromArray, WithHeadings, WithStyles
{
    public function array(): array
    {
        // Satu baris contoh untuk membantu user memahami format
        return [
            [
                'KTR-2024-000001',  // nokontrak
                'MRB',              // kdprd
                'MMB',              // pokpby
                '50000000',         // osmdlc
                '1',                // colbaru
                '',                 // tglwo (yyyymmdd, kosong jika tidak WO)
                'A',                // stsrec (A=Aktif)
                '',                 // stsacc (W=Writeoff, kosong jika tidak)
                '20200101',         // tgleff (yyyymmdd)
                '20250101',         // tglexp (yyyymmdd)
                '202612',           // periode (yyyymm)
            ],
        ];
    }

    public function headings(): array
    {
        return [
            'nokontrak',
            'kdprd',
            'pokpby',
            'osmdlc',
            'colbaru',
            'tglwo',
            'stsrec',
            'stsacc',
            'tgleff',
            'tglexp',
            'periode',
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            // Bold heading row
            1 => ['font' => ['bold' => true]],
        ];
    }
}
