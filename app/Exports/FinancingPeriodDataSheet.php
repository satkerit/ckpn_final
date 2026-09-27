<?php

declare(strict_types=1);

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class FinancingPeriodDataSheet implements FromArray, WithHeadings, WithStyles, WithTitle
{
    public function title(): string
    {
        return 'Data';
    }

    public function array(): array
    {
        return [
            [
                'KTR-2024-000001', // nokontrak
                'MRB',             // kdprd
                'MMB',             // pokpby
                '10000000',        // osmdlc
                '500000',          // ppka (nilai PPKA, angka; kosong jika tidak ada)
                '1',               // colbaru (1-5)
                '0',               // tgkhari (hari tunggakan, angka >= 0)
                '',                // tgkmdl (yyyymmdd, tanggal mulai menunggak, kosong jika tidak menunggak)
                '',                // tglwo (yyyymmdd, kosong jika tidak WO)
                'A',               // stsrec (A=Aktif, L=Lunas, dst)
                '',                // stsacc (W=WriteOff, kosong jika tidak)
                '20200101',        // tgleff (yyyymmdd)
                '20250101',        // tglexp (yyyymmdd)
                '202401',          // periode (yyyymm)
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
            'ppka',
            'colbaru',
            'tgkhari',
            'tgkmdl',
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
            1 => ['font' => ['bold' => true]],
        ];
    }
}
