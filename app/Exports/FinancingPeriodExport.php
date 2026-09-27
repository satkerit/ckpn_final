<?php

declare(strict_types=1);

namespace App\Exports;

use App\Enums\UsageType;
use App\Models\FinancingAccountPeriod;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Export daftar pembiayaan per periode sebagai sumber data CKPN.
 * Ref: PRD Bab 15
 */
class FinancingPeriodExport implements FromQuery, ShouldAutoSize, WithChunkReading, WithHeadings, WithMapping, WithStyles, WithTitle
{
    public function __construct(
        private readonly string $filterPeriod = '',
        private readonly string $filterAccount = '',
        private readonly string $filterStatus = '',
    ) {}

    public function title(): string
    {
        return 'Daftar Pembiayaan';
    }

    public function chunkSize(): int
    {
        return 500;
    }

    public function query(): Builder
    {
        $q = FinancingAccountPeriod::query()
            ->select([
                'financing_account_periods.id as id',
                'financing_account_periods.financing_account_id',
                'financing_account_periods.period',
                'financing_account_periods.outstanding_balance',
                'financing_account_periods.ppka',
                'financing_account_periods.collectibility',
                'financing_account_periods.tgkhari',
                'financing_account_periods.tgkmdl',
                DB::raw('financing_account_periods.financing_status as financing_status_raw'),
                DB::raw('financing_account_periods.writeoff_status as writeoff_status_raw'),
                'financing_account_periods.origination_date',
                'financing_account_periods.maturity_date',
                'financing_accounts.account_number',
                'financing_accounts.customer_name',
                'financing_accounts.usage_type',
            ])
            ->join('financing_accounts', 'financing_accounts.id', '=', 'financing_account_periods.financing_account_id')
            ->orderByDesc('financing_account_periods.period')
            ->orderBy('financing_accounts.account_number');

        if ($this->filterPeriod !== '') {
            $q->where('financing_account_periods.period', $this->filterPeriod);
        }

        if ($this->filterStatus !== '') {
            $q->where('financing_account_periods.financing_status', $this->filterStatus);
        }

        if ($this->filterAccount !== '') {
            $term = '%'.$this->filterAccount.'%';
            $q->where(function ($sub) use ($term) {
                $sub->where('financing_accounts.account_number', 'like', $term)
                    ->orWhere('financing_accounts.customer_name', 'like', $term);
            });
        }

        return $q;
    }

    public function headings(): array
    {
        return [
            'No. Rekening',
            'Nama Nasabah',
            'Jenis Penggunaan',
            'Periode',
            'Outstanding (Rp)',
            'PPKA (Rp)',
            'Kolektibilitas',
            'TGK Hari',
            'TGK Modal',
            'Status Pembiayaan',
            'Status Hapus Buku',
            'Tgl. Akad',
            'Tgl. Jatuh Tempo',
        ];
    }

    public function map($row): array
    {
        return [
            $row->account_number,
            $row->customer_name,
            $row->usage_type instanceof UsageType ? $row->usage_type->label() : (string) $row->usage_type,
            $row->period,
            number_format((float) $row->outstanding_balance, 2, '.', ','),
            $row->ppka !== null ? number_format((float) $row->ppka, 2, '.', ',') : '',
            $row->collectibility,
            $row->tgkhari,
            $row->tgkmdl,
            $row->financing_status_raw ?? '-',
            $row->writeoff_status_raw ?? '-',
            $row->origination_date,
            $row->maturity_date,
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => ['font' => ['bold' => true]],
        ];
    }
}
