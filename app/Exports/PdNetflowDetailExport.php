<?php

declare(strict_types=1);

namespace App\Exports;

use App\Enums\UsageType;
use App\Models\PdNetflowBucketMovement;
use App\Models\PdNetflowCompoundRate;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Export detail PD Netflow: outstanding per bucket, transition rate, dan compound flow loss.
 * Ref: PRD Bab 7
 */
class PdNetflowDetailExport implements FromCollection, ShouldAutoSize, WithHeadings, WithMapping, WithStyles, WithTitle
{
    public function __construct(
        private readonly string $calculationPeriod,
        private readonly ?int $usageTypeValue = null,
    ) {}

    public function title(): string
    {
        return "PD Netflow {$this->calculationPeriod}";
    }

    public function headings(): array
    {
        return [
            'Segmen',
            'Bucket',
            'Periode',
            'Outstanding Awal (Rp)',
            'Outstanding Akhir (Rp)',
            'Transition Rate (%)',
            'Compound Flow Loss (%)',
            'Proyeksi',
        ];
    }

    public function collection(): Collection
    {
        // Load compound rates untuk lookup: [usage_type][from_bucket_id] => compound_rate
        $compoundRates = PdNetflowCompoundRate::where('start_period', $this->calculationPeriod)
            ->when($this->usageTypeValue, fn ($q) => $q->where('usage_type', $this->usageTypeValue))
            ->get()
            ->keyBy(fn ($r) => "{$r->usage_type->value}_{$r->from_bucket_id}");

        $query = PdNetflowBucketMovement::with('fromBucket')
            ->where('period', $this->calculationPeriod)
            ->when($this->usageTypeValue, fn ($q) => $q->where('usage_type', $this->usageTypeValue))
            ->orderBy('usage_type')
            ->orderBy('from_bucket_id')
            ->orderBy('period');

        return $query->get()->map(function ($row) use ($compoundRates) {
            $key = "{$row->usage_type->value}_{$row->from_bucket_id}";
            $row->compound_rate = $compoundRates[$key]?->compound_rate ?? null;

            return $row;
        });
    }

    public function map($row): array
    {
        return [
            $row->usage_type instanceof UsageType ? $row->usage_type->label() : UsageType::tryFrom((int) $row->usage_type)?->label() ?? '-',
            $row->fromBucket?->label ?? $row->from_bucket_id,
            $row->period,
            number_format((float) $row->source_outstanding, 2, '.', ','),
            number_format((float) $row->destination_outstanding, 2, '.', ','),
            $row->transition_rate !== null ? number_format((float) $row->transition_rate * 100, 4).'%' : '-',
            $row->compound_rate !== null ? number_format((float) $row->compound_rate * 100, 4).'%' : '-',
            $row->is_projected ? 'Ya' : 'Tidak',
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => ['font' => ['bold' => true]],
        ];
    }
}
