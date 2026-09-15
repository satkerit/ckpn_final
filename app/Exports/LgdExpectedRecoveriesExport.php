<?php

declare(strict_types=1);

namespace App\Exports;

use App\Models\LgdExpectedRecoveriesResult;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Export snapshot hasil LGD Expected Recoveries per periode (+ filter jenis penggunaan).
 * Ref: PRD Bab 9
 */
class LgdExpectedRecoveriesExport implements FromCollection, ShouldAutoSize, WithHeadings, WithMapping, WithStyles, WithTitle
{
    public function __construct(
        private readonly string $calculationPeriod = '',
        private readonly ?int $usageTypeValue = null,
    ) {}

    public function title(): string
    {
        return 'LGD Expected Recoveries';
    }

    public function headings(): array
    {
        return [
            'Periode',
            'Jenis Penggunaan',
            'Periode Data Mulai',
            'Periode Data Akhir',
            'Jendela (Tahun)',
            'Total Write-off (Rp)',
            'Total Recovery (Rp)',
            'Expected Recovery Rate (%)',
            'LGD ER Rate (%)',
            'Semua Akun',
        ];
    }

    public function collection(): Collection
    {
        return LgdExpectedRecoveriesResult::query()
            ->with('calculationRunLog')
            ->when($this->calculationPeriod !== '', fn ($q) => $q->where('calculation_period', $this->calculationPeriod))
            ->when($this->usageTypeValue !== null, fn ($q) => $q->where('usage_type', $this->usageTypeValue))
            ->orderByDesc('calculation_period')
            ->orderBy('usage_type')
            ->get();
    }

    public function map($row): array
    {
        return [
            $row->calculation_period,
            $row->usage_type?->label() ?? '-',
            $row->data_period_start,
            $row->data_period_end,
            $row->window_years,
            number_format((float) $row->total_writeoff_amount, 2, '.', ','),
            number_format((float) $row->total_recovery_amount, 2, '.', ','),
            number_format((float) $row->expected_recovery_rate * 100, 4),
            number_format((float) $row->lgd_rate * 100, 4),
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
