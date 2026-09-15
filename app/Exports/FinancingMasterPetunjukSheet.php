<?php

declare(strict_types=1);

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithTitle;

class FinancingMasterPetunjukSheet implements FromArray, WithTitle
{
    public function title(): string
    {
        return 'Petunjuk';
    }

    public function array(): array
    {
        return [
            ['Kolom', 'Keterangan', 'Contoh', 'Wajib'],
            ['account_number', 'Nomor kontrak / rekening pembiayaan', 'KTR-2024-000001', 'Ya'],
            ['customer_name', 'Nama nasabah', 'Budi Santoso', 'Tidak'],
            ['product_code', 'Kode produk pembiayaan', 'MRB', 'Tidak'],
            ['akad_code', 'Kode akad / jenis akad', 'MMB', 'Tidak'],
            ['office_code', 'Kode kantor cabang/kas', 'KCP001', 'Tidak'],
            ['economic_sector', 'Kode sektor ekonomi', 'A', 'Tidak'],
            ['usage_type', 'Jenis penggunaan: 1=Modal Kerja, 2=Investasi, 3=Konsumsi', '1', 'Tidak'],
        ];
    }
}
