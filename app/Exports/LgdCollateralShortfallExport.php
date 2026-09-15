<?php

declare(strict_types=1);

namespace App\Exports;

use App\Models\LgdCollateralShortfallResult;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Export snapshot hasil LGD Collateral Shortfall per periode (+ filter jenis penggunaan).
 * Ref: PRD Bab 10
 */
class LgdCollateralShortfallExport implements FromCollection, ShouldAutoSize, WithHeadings, WithMapping, WithStyles, WithTitle
{
    public function __construct(
        private readonly string $calculationPeriod = '',
        private readonly ?int $usageTypeValue = null,
    ) {}

    public function title(): string
    {
        return 'LGD Collateral Shortfall';
    }

    public function headings(): array
    {
        return [
            'Periode',
            'Jenis Penggunaan',
            'Financing Code',
            'Outstanding (Rp)',
            'Nilai Agunan (Rp)',
            'Shortfall (Rp)',
            'LGD Rate (%)',
        ];
    }

    public function collection(): Collection
    {
        return LgdCollateralShortfallResult::query()
            ->with(['financingAccount', 'calculationRunLog'])
            ->when($this->calculationPeriod !== '', fn ($q) => $q->where('calculation_period', $this->calculationPeriod))
            ->when($this->usageTypeValue !== null, fn ($q) => $q->where('usage_type', $this->usageTypeValue))
            ->orderByDesc('calculation_period')
            ->orderBy('usage_type')
            ->orderBy('financing_account_id')
            ->get();
    }

    public function map($row): array
    {
        return [
            $row->calculation_period,
            $row->usage_type?->label() ?? '-',
            $row->financingAccount?->account_number ?? (string) $row->financing_account_id,
            number_format((float) $row->outstanding_balance, 2, '.', ','),
            number_format((float) $row->collateral_net_value, 2, '.', ','),
            number_format((float) $row->shortfall, 2, '.', ','),
            number_format((float) $row->lgd_rate * 100, 4),
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => ['font' => ['bold' => true]],
        ];
    }
}
