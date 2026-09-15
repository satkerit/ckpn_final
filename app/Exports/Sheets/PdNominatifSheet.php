<?php

declare(strict_types=1);

namespace App\Exports\Sheets;

use App\Enums\UsageType;
use App\Models\FinancingAccountPeriod;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Sheet 5 — Data dasar nominatif penetapan PD per akun per periode.
 * Ref: PRD Bab 7 & 8
 */
class PdNominatifSheet implements FromQuery, WithChunkReading, WithColumnWidths, WithHeadings, WithMapping, WithStyles, WithTitle
{
    private const COLOR_HEADER = '37474F';

    public function __construct(
        private readonly string $filterPeriod,
        private readonly string $search,
    ) {}

    public function title(): string
    {
        return 'Nominatif PD';
    }

    public function chunkSize(): int
    {
        return 500;
    }

    public function query(): Builder
    {
        $q = FinancingAccountPeriod::query()
            ->select([
                'financing_account_periods.financing_account_id',
                'financing_account_periods.period',
                'financing_account_periods.outstanding_balance',
                'financing_account_periods.collectibility',
                'financing_account_periods.tgkhari',
                'financing_account_periods.tgkmdl',
                // Baca sebagai string mentah agar tidak error jika ada nilai di luar enum
                DB::raw('financing_account_periods.financing_status as financing_status_raw'),
                DB::raw('financing_account_periods.writeoff_status as writeoff_status_raw'),
                'financing_account_periods.origination_date',
                'financing_account_periods.maturity_date',
                'financing_accounts.account_number',
                'financing_accounts.customer_name',
                'financing_accounts.usage_type',
            ])
            ->join('financing_accounts', 'financing_accounts.id', '=', 'financing_account_periods.financing_account_id')
            ->orderBy('financing_account_periods.period', 'desc')
            ->orderBy('financing_accounts.customer_name');

        if ($this->filterPeriod !== '') {
            $q->where('financing_account_periods.period', $this->filterPeriod);
        }
        if ($this->search !== '') {
            $q->where(function ($sub): void {
                $sub->where('financing_accounts.account_number', 'like', '%'.$this->search.'%')
                    ->orWhere('financing_accounts.customer_name', 'like', '%'.$this->search.'%');
            });
        }

        return $q;
    }

    public function headings(): array
    {
        return [
            'No. Akun',
            'Nama Nasabah',
            'Jenis Penggunaan',
            'Periode',
            'Outstanding (Rp)',
            'Kolektibilitas',
            'Hari Tunggakan',
            'Tunggakan Modal (Rp)',
            'Status Pembiayaan',
            'Status Write-Off',
            'Tgl. Akad',
            'Tgl. Jatuh Tempo',
        ];
    }

    public function map($row): array
    {
        $usageLabel = $row->usage_type instanceof UsageType
            ? $row->usage_type->label()
            : (string) ($row->usage_type ?? '-');

        return [
            $row->account_number,
            $row->customer_name,
            $usageLabel,
            $row->period,
            (float) $row->outstanding_balance,
            $row->collectibility ?? '-',
            (int) ($row->tgkhari ?? 0),
            (float) ($row->tgkmdl ?? 0),
            (string) ($row->financing_status_raw ?? '-'),
            (string) ($row->writeoff_status_raw ?? '-'),
            $row->origination_date ?? '-',
            $row->maturity_date ?? '-',
        ];
    }

    public function columnWidths(): array
    {
        return [
            'A' => 20, // No. Akun
            'B' => 30, // Nama Nasabah
            'C' => 18, // Jenis Penggunaan
            'D' => 14, // Periode
            'E' => 22, // Outstanding (Rp)
            'F' => 16, // Kolektibilitas
            'G' => 14, // TGK Hari
            'H' => 14, // TGK Modal
            'I' => 18, // Status Pembiayaan
            'J' => 16, // Status Hapus Buku
            'K' => 16, // Tgl. Akad
            'L' => 16, // Tgl. Jatuh Tempo
        ];
    }

    public function styles(Worksheet $sheet): ?array
    {
        $sheet->getStyle('A1:L1')->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF'], 'size' => 10],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => self::COLOR_HEADER]],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => '90A4AE']]],
        ]);
        $sheet->getRowDimension(1)->setRowHeight(30);
        $sheet->freezePane('A2');

        return null;
    }
}
