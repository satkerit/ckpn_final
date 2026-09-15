<?php

declare(strict_types=1);

namespace App\Exports\Sheets;

use App\Enums\UsageType;
use App\Models\CkpnCollectiveResult;
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
 * Sheet 2 — Detail per nasabah CKPN Kolektif.
 * Ref: PRD Bab 11
 */
class KolektifSheet implements FromQuery, WithChunkReading, WithColumnWidths, WithHeadings, WithMapping, WithStyles, WithTitle
{
    private const COLOR_HEADER = '1565C0';

    public function __construct(
        private readonly string $filterPeriod,
        private readonly string $filterUsageType,
        private readonly string $search,
    ) {}

    public function title(): string
    {
        return 'CKPN Kolektif';
    }

    public function chunkSize(): int
    {
        return 500;
    }

    public function query(): Builder
    {
        $q = CkpnCollectiveResult::query()
            ->select([
                'ckpn_collective_result.id',
                'ckpn_collective_result.calculation_period',
                'ckpn_collective_result.usage_type',
                'ckpn_collective_result.pd_method_used',
                'ckpn_collective_result.pd_rate',
                'ckpn_collective_result.lgd_method_used',
                'ckpn_collective_result.lgd_rate',
                'ckpn_collective_result.ead',
                'ckpn_collective_result.ckpn_amount',
                'financing_accounts.account_number',
                'financing_accounts.customer_name',
                DB::raw('(SELECT COALESCE(SUM(c.estimated_sale_value), 0)
                          FROM collaterals c
                          WHERE c.financing_account_id = ckpn_collective_result.financing_account_id
                            AND c.is_active = 1) as total_collateral_value'),
            ])
            ->join('financing_accounts', 'financing_accounts.id', '=', 'ckpn_collective_result.financing_account_id')
            ->orderBy('ckpn_collective_result.calculation_period', 'desc')
            ->orderBy('financing_accounts.customer_name');

        if ($this->filterPeriod !== '') {
            $q->where('ckpn_collective_result.calculation_period', $this->filterPeriod);
        }
        if ($this->filterUsageType !== '') {
            $q->where('ckpn_collective_result.usage_type', $this->filterUsageType);
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
            'Periode',
            'Jenis Penggunaan',
            'Metode PD',
            'Rate PD (%)',
            'Metode LGD',
            'Rate LGD (%)',
            'EAD (Rp)',
            'Nilai Agunan (Rp)',
            'CKPN (Rp)',
        ];
    }

    public function map($row): array
    {
        return [
            $row->account_number,
            $row->customer_name,
            $row->calculation_period,
            $row->usage_type instanceof UsageType
                ? $row->usage_type->label()
                : (string) ($row->usage_type ?? '-'),
            $row->pd_method_used ?? '-',
            round((float) $row->pd_rate * 100, 4),
            $row->lgd_method_used ?? '-',
            round((float) $row->lgd_rate * 100, 4),
            (float) $row->ead,
            (float) $row->total_collateral_value,
            (float) $row->ckpn_amount,
        ];
    }

    public function columnWidths(): array
    {
        return [
            'A' => 20, // No. Akun
            'B' => 30, // Nama Nasabah
            'C' => 14, // Periode
            'D' => 18, // Jenis Penggunaan
            'E' => 16, // Metode PD
            'F' => 14, // PD Rate (%)
            'G' => 16, // Metode LGD
            'H' => 14, // LGD Rate (%)
            'I' => 18, // EAD (Rp)
            'J' => 22, // Nilai Agunan (Rp)
            'K' => 18, // CKPN (Rp)
        ];
    }

    public function styles(Worksheet $sheet): ?array
    {
        $sheet->getStyle('A1:K1')->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF'], 'size' => 10],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => self::COLOR_HEADER]],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => '90CAF9']]],
        ]);
        $sheet->getRowDimension(1)->setRowHeight(30);
        $sheet->freezePane('A2');

        return null;
    }
}
