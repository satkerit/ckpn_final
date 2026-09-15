<?php

declare(strict_types=1);

namespace App\Exports;

use App\Enums\ClassificationType;
use App\Enums\UsageType;
use App\Models\CkpnPeriodClassification;
use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Export data klasifikasi pembiayaan CKPN (Individual vs Kolektif) ke Excel.
 * Ref: PRD Bab 6.1, 12a Step 3
 */
class CkpnClassificationExport implements FromQuery, ShouldAutoSize, WithChunkReading, WithHeadings, WithMapping, WithStyles, WithTitle
{
    public function __construct(
        private readonly string $filterPeriod = '',
        private readonly string $filterUsageType = '',
        private readonly string $filterClassification = '',
        private readonly string $search = '',
    ) {}

    public function title(): string
    {
        return 'Klasifikasi CKPN';
    }

    public function chunkSize(): int
    {
        return 1000;
    }

    public function query(): Builder
    {
        $q = CkpnPeriodClassification::query()
            ->select([
                'ckpn_period_classifications.period',
                'ckpn_period_classifications.classification',
                'ckpn_period_classifications.outstanding_balance',
                'ckpn_period_classifications.collectibility',
                'ckpn_period_classifications.financing_status',
                'ckpn_period_classifications.writeoff_status',
                'ckpn_period_classifications.usage_type',
                'ckpn_period_classifications.classification_reason',
                'financing_accounts.account_number',
                'financing_accounts.customer_name',
                'financing_accounts.product_code',
                'financing_accounts.akad_code',
            ])
            ->join('financing_accounts', 'financing_accounts.id', '=', 'ckpn_period_classifications.financing_account_id')
            ->orderByDesc('ckpn_period_classifications.period')
            ->orderBy('ckpn_period_classifications.usage_type')
            ->orderBy('financing_accounts.account_number');

        if ($this->filterPeriod !== '') {
            $q->where('ckpn_period_classifications.period', $this->filterPeriod);
        }

        if ($this->filterUsageType !== '') {
            $q->where('ckpn_period_classifications.usage_type', (int) $this->filterUsageType);
        }

        if ($this->filterClassification !== '') {
            $q->where('ckpn_period_classifications.classification', $this->filterClassification);
        }

        if ($this->search !== '') {
            $term = '%'.$this->search.'%';
            $q->where(function ($w) use ($term): void {
                $w->where('ckpn_period_classifications.period', 'like', $term)
                    ->orWhere('financing_accounts.account_number', 'like', $term)
                    ->orWhere('financing_accounts.customer_name', 'like', $term);
            });
        }

        return $q;
    }

    public function headings(): array
    {
        return [
            'Periode',
            'No Kontrak / Rekening',
            'Nama Debitur',
            'Kode Produk',
            'Kode Akad',
            'Jenis Penggunaan',
            'Klasifikasi',
            'Outstanding / EAD (Rp)',
            'Kolektibilitas',
            'Status Pembiayaan',
            'Status Write-off',
            'Alasan Klasifikasi',
        ];
    }

    /**
     * @param  CkpnPeriodClassification  $row
     */
    public function map($row): array
    {
        $usageType = $row->usage_type instanceof UsageType
            ? $row->usage_type->label()
            : (is_numeric($row->usage_type) ? (UsageType::tryFrom((int) $row->usage_type)?->label() ?? (string) $row->usage_type) : '-');

        $classificationLabel = $row->classification instanceof ClassificationType
            ? $row->classification->label()
            : (is_string($row->classification) ? (ClassificationType::tryFrom($row->classification)?->label() ?? $row->classification) : '-');

        return [
            $row->period,
            $row->account_number ?? '-',
            $row->customer_name ?? '-',
            $row->product_code ?? '-',
            $row->akad_code ?? '-',
            $usageType,
            $classificationLabel,
            (float) $row->outstanding_balance,
            (string) ($row->collectibility ?? '-'),
            (string) ($row->financing_status?->value ?? $row->financing_status ?? '-'),
            (string) ($row->writeoff_status?->value ?? $row->writeoff_status ?? '-'),
            $row->classification_reason ?? '-',
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => [
                'font' => ['bold' => true, 'color' => ['argb' => 'FFFFFFFF']],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['argb' => 'FF1E3A8A'],
                ],
            ],
        ];
    }
}
