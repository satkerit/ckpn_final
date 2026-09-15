<?php

declare(strict_types=1);

namespace App\Exports;

use App\Enums\UsageType;
use App\Models\PdNetflowResult;
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
 * Export daftar nasabah sumber perhitungan PD Netflow per periode & segmen.
 * Ref: PRD Bab 7
 */
class PdNetflowSourceExport implements FromQuery, ShouldAutoSize, WithChunkReading, WithHeadings, WithMapping, WithStyles, WithTitle
{
    public function __construct(
        private readonly string $filterPeriode = '',
        private readonly string $filterUsageType = '',
        private readonly string $search = '',
    ) {}

    public function title(): string
    {
        return 'Nasabah PD Netflow';
    }

    public function chunkSize(): int
    {
        return 500;
    }

    public function query(): Builder
    {
        $q = PdNetflowResult::query()
            ->select([
                'pd_netflow_result.id',
                'pd_netflow_result.calculation_period',
                'pd_netflow_result.usage_type',
                'pd_netflow_result.bucket_from',
                'pd_netflow_result.bucket_to',
                'pd_netflow_result.account_count',
                'pd_netflow_result.outstanding_amount',
                'pd_netflow_result.netflow_rate',
                'pd_netflow_result.data_period_start',
                'pd_netflow_result.data_period_end',
            ])
            ->orderByDesc('pd_netflow_result.calculation_period')
            ->orderBy('pd_netflow_result.usage_type')
            ->orderBy('pd_netflow_result.bucket_from');

        if ($this->filterPeriode !== '') {
            $q->where('pd_netflow_result.calculation_period', $this->filterPeriode);
        }

        if ($this->filterUsageType !== '') {
            $q->where('pd_netflow_result.usage_type', $this->filterUsageType);
        }

        if ($this->search !== '') {
            $term = '%'.$this->search.'%';
            $q->where(function ($sub) use ($term) {
                $sub->where('pd_netflow_result.calculation_period', 'like', $term)
                    ->orWhere('pd_netflow_result.usage_type', 'like', $term);
            });
        }

        return $q;
    }

    public function headings(): array
    {
        return [
            'Periode Hitung',
            'Jenis Penggunaan',
            'Bucket Asal',
            'Bucket Tujuan',
            'Jumlah Akun',
            'Outstanding (Rp)',
            'Netflow Rate (%)',
            'Periode Data Mulai',
            'Periode Data Akhir',
        ];
    }

    public function map($row): array
    {
        return [
            $row->calculation_period,
            $row->usage_type instanceof UsageType ? $row->usage_type->label() : (string) $row->usage_type,
            $row->bucket_from,
            $row->bucket_to,
            $row->account_count,
            number_format((float) $row->outstanding_amount, 2, '.', ','),
            number_format((float) $row->netflow_rate * 100, 6),
            $row->data_period_start,
            $row->data_period_end,
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => ['font' => ['bold' => true]],
        ];
    }
}
