<?php

declare(strict_types=1);

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithTitle;

class FinancingPeriodPetunjukSheet implements FromArray, WithTitle
{
    public function title(): string
    {
        return 'Petunjuk';
    }

    public function array(): array
    {
        return [
            ['Kolom', 'Keterangan', 'Contoh', 'Wajib'],
            ['nokontrak', 'Nomor kontrak / rekening pembiayaan', 'KTR-2024-000001', 'Ya'],
            ['kdprd', 'Kode produk pembiayaan', 'MRB', 'Tidak'],
            ['pokpby', 'Kode akad / jenis akad', 'MMB', 'Tidak'],
            ['osmdlc', 'Saldo outstanding / pokok berjalan (angka)', '10000000', 'Ya'],
            ['colbaru', 'Kolektibilitas baru: 1=Lancar, 2=DPK, 3=Kurang Lancar, 4=Diragukan, 5=Macet', '1', 'Ya'],
            ['tglwo', 'Tanggal write-off, format yyyymmdd (kosong jika tidak WO)', '20240101', 'Tidak'],
            ['stsrec', 'Status rekening: A=Aktif, L=Lunas, H=Hapus Buku, dll', 'A', 'Tidak'],
            ['stsacc', 'Status akuntansi: W=WriteOff (kosong jika tidak)', 'W', 'Tidak'],
            ['tgleff', 'Tanggal efektif / akad, format yyyymmdd', '20200101', 'Tidak'],
            ['tglexp', 'Tanggal jatuh tempo, format yyyymmdd', '20250101', 'Tidak'],
            ['periode', 'Periode data (yyyymm) — WAJIB unik per nokontrak+periode', '202401', 'Ya'],
        ];
    }
}
