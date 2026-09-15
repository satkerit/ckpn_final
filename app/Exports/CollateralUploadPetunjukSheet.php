<?php

declare(strict_types=1);

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithTitle;

class CollateralUploadPetunjukSheet implements FromArray, WithTitle
{
    public function title(): string
    {
        return 'Petunjuk';
    }

    public function array(): array
    {
        return [
            ['Kolom', 'Keterangan', 'Contoh', 'Wajib'],
            ['account_number', 'Nomor kontrak / rekening pembiayaan (harus sudah ada di sistem)', 'KTR-2024-000001', 'Ya'],
            ['collateral_code', 'Kode unik jaminan', 'AGN-001', 'Ya'],
            ['collateral_type_code', 'Kode jenis jaminan dari master (tabel collateral_types, kolom code)', 'SHM', 'Ya'],
            ['description', 'Deskripsi jaminan', 'Tanah dan Bangunan Jl. Merdeka', 'Tidak'],
            ['appraisal_value', 'Nilai taksasi/appraisal (angka, tanpa titik/koma ribuan)', '500000000', 'Tidak'],
            ['estimated_sale_value', 'Estimasi nilai jual (angka, tanpa titik/koma ribuan)', '450000000', 'Tidak'],
            ['appraised_at', 'Tanggal penilaian jaminan, format yyyymmdd', '20240101', 'Tidak'],
            ['is_active', 'Status aktif: 1=Aktif, 0=Tidak Aktif', '1', 'Tidak'],
        ];
    }
}
