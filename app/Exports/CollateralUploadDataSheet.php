<?php

declare(strict_types=1);

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class CollateralUploadDataSheet implements FromArray, WithHeadings, WithStyles, WithTitle
{
    public function title(): string
    {
        return 'Data';
    }

    public function array(): array
    {
        return [
            [
                'KTR-2024-000001',
                'AGN-001',
                1,
                'SHM',
                'Tanah dan Bangunan Jl. Merdeka No. 1',
                '500000000',
                '450000000',
                '20240101',
                '1',
            ],
        ];
    }

    public function headings(): array
    {
        return [
            'account_number',
            'collateral_code',
            'sequence_number',
            'collateral_type_code',
            'description',
            'appraisal_value',
            'estimated_sale_value',
            'appraised_at',
            'is_active',
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => ['font' => ['bold' => true]],
        ];
    }
}
