<?php

declare(strict_types=1);

namespace App\Exports;

use App\Models\CkpnCollectiveResult;
use App\Models\CkpnIndividualResult;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Export ringkasan CKPN (Individual + Kolektif + Total) ke Excel.
 * Ref: PRD FR-12
 */
class CkpnSummaryExport implements FromCollection, ShouldAutoSize, WithHeadings, WithMapping, WithStyles
{
    public function collection(): Collection
    {
        return CkpnCollectiveResult::query()
            ->select([
                'calculation_period',
                'usage_type',
                DB::raw('SUM(ckpn_amount) as total_ckpn_collective'),
                DB::raw('COUNT(*) as account_count_collective'),
                DB::raw('MIN(pd_method_used) as pd_method_used'),
            ])
            ->groupBy('calculation_period', 'usage_type')
            ->orderByDesc('calculation_period')
            ->get();
    }

    public function headings(): array
    {
        return [
            'Periode',
            'Jenis Penggunaan',
            'CKPN Kolektif (Rp)',
            'Jumlah Akun Kolektif',
            'Metode PD',
            'CKPN Individual (Rp)',
            'CKPN TOTAL (Rp)',
        ];
    }

    public function map($row): array
    {
        $individualAmount = (float) CkpnIndividualResult::where('calculation_period', $row->calculation_period)
            ->sum('ckpn_amount');
        $collectiveAmount = (float) $row->total_ckpn_collective;

        return [
            $row->calculation_period,
            $row->usage_type?->label() ?? '-',
            number_format($collectiveAmount, 2, '.', ''),
            $row->account_count_collective,
            $row->pd_method_used,
            number_format($individualAmount, 2, '.', ''),
            number_format($individualAmount + $collectiveAmount, 2, '.', ''),
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => ['font' => ['bold' => true]],
        ];
    }
}
