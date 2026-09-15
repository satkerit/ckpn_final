<?php

declare(strict_types=1);

namespace App\Exports\Sheets;

use App\Enums\UsageType;
use App\Models\LgdCollateralShortfallResult;
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
 * Sheet 7 — Data dasar pembiayaan LGD Collateral Shortfall per nasabah.
 * Ref: PRD Bab 10
 */
class LgdCsSheet implements FromQuery, WithChunkReading, WithColumnWidths, WithHeadings, WithMapping, WithStyles, WithTitle
{
    private const COLOR_HEADER = 'BF360C';

    public function __construct(
        private readonly string $filterPeriod,
        private readonly string $filterUsageType,
        private readonly string $search,
    ) {}

    public function title(): string
    {
        return 'LGD Collateral Shortfall';
    }

    public function chunkSize(): int
    {
        return 500;
    }

    public function query(): Builder
    {
        $q = LgdCollateralShortfallResult::query()
            ->select([
                'lgd_collateral_shortfall_result.calculation_period',
                'lgd_collateral_shortfall_result.usage_type',
                'lgd_collateral_shortfall_result.outstanding_balance',
                'lgd_collateral_shortfall_result.collateral_net_value',
                'lgd_collateral_shortfall_result.shortfall',
                'lgd_collateral_shortfall_result.lgd_rate',
                'lgd_collateral_shortfall_result.notes',
                'financing_accounts.account_number',
                'financing_accounts.customer_name',
                DB::raw('(SELECT COALESCE(SUM(c.estimated_sale_value), 0)
                          FROM collaterals c
                          WHERE c.financing_account_id = lgd_collateral_shortfall_result.financing_account_id
                            AND c.is_active = 1) as total_appraisal_value'),
                DB::raw('(SELECT COALESCE(SUM(c.appraisal_value), 0)
                          FROM collaterals c
                          WHERE c.financing_account_id = lgd_collateral_shortfall_result.financing_account_id
                            AND c.is_active = 1) as total_njop_value'),
            ])
            ->join('financing_accounts', 'financing_accounts.id', '=', 'lgd_collateral_shortfall_result.financing_account_id')
            ->orderBy('lgd_collateral_shortfall_result.calculation_period', 'desc')
            ->orderBy('financing_accounts.customer_name');

        if ($this->filterPeriod !== '') {
            $q->where('lgd_collateral_shortfall_result.calculation_period', $this->filterPeriod);
        }
        if ($this->filterUsageType !== '') {
            $q->where('lgd_collateral_shortfall_result.usage_type', $this->filterUsageType);
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
            'Nilai Agunan Bersih (Rp)',
            'Nilai Appraisal (Rp)',
            'Nilai NJOP (Rp)',
            'Shortfall (Rp)',
            'Rate LGD (%)',
            'Catatan',
        ];
    }

    public function map($row): array
    {
        return [
            $row->account_number,
            $row->customer_name,
            $row->usage_type instanceof UsageType
                ? $row->usage_type->label()
                : (string) ($row->usage_type ?? '-'),
            $row->calculation_period,
            (float) $row->outstanding_balance,
            (float) $row->collateral_net_value,
            (float) $row->total_appraisal_value,
            (float) $row->total_njop_value,
            (float) $row->shortfall,
            round((float) $row->lgd_rate * 100, 6),
            $row->notes ?? '',
        ];
    }

    public function columnWidths(): array
    {
        return [
            'A' => 20, // No. Akun
            'B' => 30, // Nama Nasabah
            'C' => 14, // Periode
            'D' => 18, // Jenis Penggunaan
            'E' => 22, // Outstanding (Rp)
            'F' => 22, // Nilai Bersih Agunan (Rp)
            'G' => 22, // Nilai Appraisal (Rp)
            'H' => 22, // Nilai NJOP (Rp)
            'I' => 18, // Shortfall (Rp)
            'J' => 14, // LGD Rate (%)
            'K' => 30, // Catatan
        ];
    }

    public function styles(Worksheet $sheet): ?array
    {
        $sheet->getStyle('A1:K1')->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF'], 'size' => 10],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => self::COLOR_HEADER]],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'FFAB91']]],
        ]);
        $sheet->getRowDimension(1)->setRowHeight(30);
        $sheet->freezePane('A2');

        return null;
    }
}
