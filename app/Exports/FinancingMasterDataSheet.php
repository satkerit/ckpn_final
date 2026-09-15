<?php

declare(strict_types=1);

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class FinancingMasterDataSheet implements FromArray, WithHeadings, WithStyles, WithTitle
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
                'Budi Santoso',
                'MRB',
                'MMB',
                'KCP001',
                'A',
                '1',
            ],
        ];
    }

    public function headings(): array
    {
        return [
            'account_number',
            'customer_name',
            'product_code',
            'akad_code',
            'office_code',
            'economic_sector',
            'usage_type',
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => ['font' => ['bold' => true]],
        ];
    }
}
