<?php

declare(strict_types=1);

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class LgdErExport implements FromArray, WithHeadings, WithStyles
{
    public function __construct(private readonly array $data) {}

    public function array(): array
    {
        return $this->data;
    }

    public function headings(): array
    {
        return [
            'Periode Kalkulasi',
            'Jenis Penggunaan',
            'Kode Kantor',
            'Window (Thn)',
            'LGD Rate',
            'Expected Recovery Rate',
            'Total Writeoff',
            'Total Recovery',
            'All Account',
            'Job Status',
            'Data Periode Start',
            'Data Periode End',
            'Dibuat',
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => [
                'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => ['fillType' => 'solid', 'startColor' => ['rgb' => '366092']],
                'alignment' => ['horizontal' => 'center', 'vertical' => 'center'],
            ],
        ];
    }
}
