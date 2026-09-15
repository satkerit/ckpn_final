<?php

declare(strict_types=1);

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithTitle;

class CollateralTypePetunjukSheet implements FromArray, WithTitle
{
    public function title(): string
    {
        return 'Petunjuk';
    }

    public function array(): array
    {
        return [
            ['Kolom', 'Keterangan', 'Contoh', 'Wajib'],
            ['code', 'Kode unik jenis jaminan (maks. 20 karakter)', 'SHM', 'Ya'],
            ['name', 'Nama jenis jaminan (maks. 255 karakter)', 'Sertifikat Hak Milik', 'Ya'],
            ['liquidation_discount_rate', 'Tingkat diskon likuidasi (0–1, gunakan titik sebagai desimal)', '0.80', 'Tidak'],
            ['is_active', 'Status aktif: 1=Aktif, 0=Tidak Aktif', '1', 'Tidak (default: 1)'],
        ];
    }
}
