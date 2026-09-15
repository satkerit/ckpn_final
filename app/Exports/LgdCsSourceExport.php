<?php

declare(strict_types=1);

namespace App\Exports;

use App\Enums\UsageType;
use App\Models\LgdCollateralShortfallResult;
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
 * Export daftar nasabah sumber perhitungan LGD Collateral Shortfall per periode & segmen.
 * Ref: PRD Bab 10
 */
class LgdCsSourceExport implements FromQuery, ShouldAutoSize, WithChunkReading, WithHeadings, WithMapping, WithStyles, WithTitle
{
    public function __construct(
        private readonly string $filterPeriode = '',
        private readonly string $filterUsageType = '',
        private readonly string $search = '',
    ) {}

    public function title(): string
    {
        return 'Nasabah LGD CS';
    }

    public function chunkSize(): int
    {
        return 500;
    }

    public function query(): Builder
    {
        $q = LgdCollateralShortfallResult::query()
            ->select([
                'lgd_collateral_shortfall_result.id',
                'lgd_collateral_shortfall_result.calculation_period',
                'lgd_collateral_shortfall_result.usage_type',
                'lgd_collateral_shortfall_result.financing_account_id',
                'lgd_collateral_shortfall_result.outstanding_balance',
                'lgd_collateral_shortfall_result.collateral_net_value',
                'lgd_collateral_shortfall_result.shortfall',
                'lgd_collateral_shortfall_result.lgd_rate',
                'financing_accounts.account_number',
                'financing_accounts.customer_name',
            ])
            ->join('financing_accounts', 'financing_accounts.id', '=', 'lgd_collateral_shortfall_result.financing_account_id')
            ->orderByDesc('lgd_collateral_shortfall_result.calculation_period')
            ->orderBy('lgd_collateral_shortfall_result.usage_type')
            ->orderBy('financing_accounts.account_number');

        if ($this->filterPeriode !== '') {
            $q->where('lgd_collateral_shortfall_result.calculation_period', $this->filterPeriode);
        }

        if ($this->filterUsageType !== '') {
            $q->where('lgd_collateral_shortfall_result.usage_type', $this->filterUsageType);
        }

        if ($this->search !== '') {
            $term = '%'.$this->search.'%';
            $q->where(function ($sub) use ($term) {
                $sub->where('financing_accounts.account_number', 'like', $term)
                    ->orWhere('financing_accounts.customer_name', 'like', $term)
                    ->orWhere('lgd_collateral_shortfall_result.calculation_period', 'like', $term);
            });
        }

        return $q;
    }

    public function headings(): array
    {
        return [
            'Periode Hitung',
            'Jenis Penggunaan',
            'No. Kontrak',
            'Nama Nasabah',
            'Outstanding (Rp)',
            'Nilai Agunan Bersih (Rp)',
            'Shortfall (Rp)',
            'LGD Rate (%)',
        ];
    }

    public function map($row): array
    {
        return [
            $row->calculation_period,
            $row->usage_type instanceof UsageType ? $row->usage_type->label() : (string) $row->usage_type,
            $row->account_number,
            $row->customer_name,
            number_format((float) $row->outstanding_balance, 2, '.', ','),
            number_format((float) $row->collateral_net_value, 2, '.', ','),
            number_format((float) $row->shortfall, 2, '.', ','),
            number_format((float) $row->lgd_rate * 100, 6),
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => ['font' => ['bold' => true]],
        ];
    }
}
