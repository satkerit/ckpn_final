<?php

declare(strict_types=1);

namespace App\Exports;

use App\Enums\UsageType;
use App\Models\LgdExpectedRecoveriesResult;
use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Export daftar nasabah sumber perhitungan LGD Expected Recoveries per periode & segmen.
 * Ref: PRD Bab 9
 */
class LgdErSourceExport implements FromQuery, ShouldAutoSize, WithChunkReading, WithHeadings, WithMapping, WithStyles, WithTitle
{
    public function __construct(
        private readonly string $filterPeriode = '',
        private readonly string $filterUsageType = '',
        private readonly string $search = '',
    ) {}

    public function title(): string
    {
        return 'Nasabah LGD ER';
    }

    public function chunkSize(): int
    {
        return 500;
    }

    public function query(): Builder
    {
        $q = LgdExpectedRecoveriesResult::query()
            ->select([
                'lgd_expected_recoveries_result.id',
                'lgd_expected_recoveries_result.calculation_period',
                'lgd_expected_recoveries_result.usage_type',
                'lgd_expected_recoveries_result.data_period_start',
                'lgd_expected_recoveries_result.data_period_end',
                'lgd_expected_recoveries_result.window_years',
                'lgd_expected_recoveries_result.total_writeoff_amount',
                'lgd_expected_recoveries_result.total_recovery_amount',
                'lgd_expected_recoveries_result.expected_recovery_rate',
                'lgd_expected_recoveries_result.lgd_rate',
                'lgd_expected_recoveries_result.is_all_account',
            ])
            ->orderByDesc('lgd_expected_recoveries_result.calculation_period')
            ->orderBy('lgd_expected_recoveries_result.usage_type');

        if ($this->filterPeriode !== '') {
            $q->where('lgd_expected_recoveries_result.calculation_period', $this->filterPeriode);
        }

        if ($this->filterUsageType !== '') {
            $q->where('lgd_expected_recoveries_result.usage_type', $this->filterUsageType);
        }

        if ($this->search !== '') {
            $term = '%'.$this->search.'%';
            $q->where(function ($sub) use ($term) {
                $sub->where('lgd_expected_recoveries_result.calculation_period', 'like', $term)
                    ->orWhere('lgd_expected_recoveries_result.usage_type', 'like', $term);
            });
        }

        return $q;
    }

    public function headings(): array
    {
        return [
            'Periode Hitung',
            'Jenis Penggunaan',
            'Periode Data Mulai',
            'Periode Data Akhir',
            'Rolling Window (Tahun)',
            'Total Write-Off (Rp)',
            'Total Recovery (Rp)',
            'Recovery Rate (%)',
            'LGD Rate (%)',
            'Berbasis Semua Akun',
        ];
    }

    public function map($row): array
    {
        return [
            $row->calculation_period,
            $row->usage_type instanceof UsageType ? $row->usage_type->label() : (string) $row->usage_type,
            $row->data_period_start,
            $row->data_period_end,
            $row->window_years,
            number_format((float) $row->total_writeoff_amount, 2, '.', ','),
            number_format((float) $row->total_recovery_amount, 2, '.', ','),
            number_format((float) $row->expected_recovery_rate * 100, 6),
            number_format((float) $row->lgd_rate * 100, 6),
            $row->is_all_account ? 'Ya' : 'Tidak',
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => ['font' => ['bold' => true]],
        ];
    }
}
