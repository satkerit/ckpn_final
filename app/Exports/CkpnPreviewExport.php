<?php

declare(strict_types=1);

namespace App\Exports;

use App\Domain\Ckpn\Preview\CkpnPreviewResult;
use App\Domain\Ckpn\Preview\CkpnPreviewRow;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Export daftar nasabah klasifikasi CKPN (Individual / Kolektif)
 * dari hasil preview — lengkap akad_code & tgl jatuh tempo.
 */
class CkpnPreviewExport implements FromCollection, ShouldAutoSize, WithHeadings, WithMapping, WithStyles
{
    private CkpnPreviewResult $result;

    public function __construct(CkpnPreviewResult $result)
    {
        $this->result = $result;
    }

    public function collection(): Collection
    {
        // Gabung individual + kolektif, beri flag klasifikasi
        $individual = $this->result->individual->map(
            fn (CkpnPreviewRow $r) => (object) ['klasifikasi' => 'Individual', 'row' => $r]
        );
        $collective = $this->result->collective->map(
            fn (CkpnPreviewRow $r) => (object) ['klasifikasi' => 'Kolektif', 'row' => $r]
        );

        return $individual->merge($collective);
    }

    public function headings(): array
    {
        return [
            'Klasifikasi',
            'No Kontrak',
            'Nama Nasabah',
            'Akad Code',
            'Tgl Jatuh Tempo',
            'Segmen',
            'Kolektibilitas',
            'EAD (Rp)',
            'PD (%)',
            'Metode PD',
            'LGD (%)',
            'Metode LGD',
            'CKPN (Rp)',
            'Nilai Mitigasi (Rp)',
            'Golongan Penjamin',
            'Tgl Penilaian Terakhir',
            'Validitas Penilaian',
        ];
    }

    public function map($item): array
    {
        $row = $item->row;

        return [
            $item->klasifikasi,
            $row->accountNumber,
            $row->customerName,
            $row->akadCode ?? '-',
            $row->maturityDate ?? '-',
            $row->usageType->label(),
            (string) $row->collectibility,
            $row->ead,
            round($row->pdRate * 100, 4),
            $row->pdMethodUsed,
            round($row->lgdRate * 100, 4),
            $row->lgdMethodUsed,
            $row->ckpnAmount,
            $row->mitigationValue,
            $row->penjaminGroup ?: '-',
            $row->lastAppraisalDate ?? '-',
            $row->appraisalValid ? 'Valid' : 'Kedaluwarsa',
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => ['font' => ['bold' => true]],
        ];
    }
}
