<?php

declare(strict_types=1);

namespace App\Exports\Sheets;

use App\Models\CkpnIndividualResult;
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
 * Sheet 3 — Detail per nasabah CKPN Individual.
 * Ref: PRD Bab 6.1
 */
class IndividualSheet implements FromQuery, WithChunkReading, WithColumnWidths, WithHeadings, WithMapping, WithStyles, WithTitle
{
    private const COLOR_HEADER = '1B5E20';

    public function __construct(
        private readonly string $filterPeriod,
        private readonly string $search,
    ) {}

    public function title(): string
    {
        return 'CKPN Individual';
    }

    public function chunkSize(): int
    {
        return 500;
    }

    public function query(): Builder
    {
        $q = CkpnIndividualResult::query()
            ->select([
                'ckpn_individual_result.id',
                'ckpn_individual_result.calculation_period',
                'ckpn_individual_result.collectibility',
                'ckpn_individual_result.outstanding_balance',
                'ckpn_individual_result.total_collateral_liquidation_value',
                'ckpn_individual_result.ckpn_amount',
                'financing_accounts.account_number',
                'financing_accounts.customer_name',
                DB::raw('(SELECT COALESCE(SUM(c.estimated_sale_value), 0)
                          FROM collaterals c
                          WHERE c.financing_account_id = ckpn_individual_result.financing_account_id
                            AND c.is_active = 1) as total_collateral_value'),
            ])
            ->join('financing_accounts', 'financing_accounts.id', '=', 'ckpn_individual_result.financing_account_id')
            ->orderBy('ckpn_individual_result.calculation_period', 'desc')
            ->orderBy('financing_accounts.customer_name');

        if ($this->filterPeriod !== '') {
            $q->where('ckpn_individual_result.calculation_period', $this->filterPeriod);
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
            'Kolektibilitas',
            'Outstanding / EAD (Rp)',
            'Nilai Agunan (Rp)',
            'Nilai Likuidasi Agunan (Rp)',
            'CKPN (Rp)',
        ];
    }

    public function map($row): array
    {
        return [
            $row->account_number,
            $row->customer_name,
            $row->calculation_period,
            $row->collectibility ?? '-',
            (float) $row->outstanding_balance,
            (float) $row->total_collateral_value,
            (float) $row->total_collateral_liquidation_value,
            (float) $row->ckpn_amount,
        ];
    }

    public function columnWidths(): array
    {
        return [
            'A' => 20, // No. Akun
            'B' => 30, // Nama Nasabah
            'C' => 14, // Periode
            'D' => 14, // Kolektibilitas
            'E' => 20, // Outstanding (Rp)
            'F' => 22, // Nilai Agunan (Rp)
            'G' => 26, // Nilai Likuidasi Agunan (Rp)
            'H' => 18, // CKPN (Rp)
        ];
    }

    public function styles(Worksheet $sheet): ?array
    {
        $sheet->getStyle('A1:H1')->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF'], 'size' => 10],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => self::COLOR_HEADER]],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'A5D6A7']]],
        ]);
        $sheet->getRowDimension(1)->setRowHeight(30);
        $sheet->freezePane('A2');

        return null;
    }
}
