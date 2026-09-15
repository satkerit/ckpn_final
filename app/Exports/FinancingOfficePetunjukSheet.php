<?php

declare(strict_types=1);

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithTitle;

class FinancingOfficePetunjukSheet implements FromArray, WithTitle
{
    public function title(): string
    {
        return 'Petunjuk';
    }

    public function array(): array
    {
        return [
            ['Kolom', 'Keterangan', 'Contoh', 'Wajib'],
            ['code', 'Kode unik kantor (maks. 20 karakter)', 'KCP001', 'Ya'],
            ['name', 'Nama kantor (maks. 255 karakter)', 'Kantor Cabang Pembantu 001', 'Ya'],
            ['is_active', 'Status aktif: 1=Aktif, 0=Tidak Aktif', '1', 'Tidak (default: 1)'],
        ];
    }
}
