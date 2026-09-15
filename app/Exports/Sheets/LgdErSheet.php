<?php

declare(strict_types=1);

namespace App\Exports\Sheets;

use App\Enums\UsageType;
use App\Models\LgdExpectedRecoveriesResult;
use Illuminate\Database\Eloquent\Builder;
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
 * Sheet 6 — Data dasar pembiayaan LGD Expected Recoveries per segmen.
 * Ref: PRD Bab 9
 */
class LgdErSheet implements FromQuery, WithChunkReading, WithColumnWidths, WithHeadings, WithMapping, WithStyles, WithTitle
{
    private const COLOR_HEADER = '004D40';

    public function __construct(
        private readonly string $filterPeriod,
        private readonly string $filterUsageType,
    ) {}

    public function title(): string
    {
        return 'LGD Expected Recoveries';
    }

    public function chunkSize(): int
    {
        return 500;
    }

    public function query(): Builder
    {
        $q = LgdExpectedRecoveriesResult::query()
            ->select([
                'calculation_period',
                'usage_type',
                'data_period_start',
                'data_period_end',
                'window_years',
                'total_writeoff_amount',
                'total_recovery_amount',
                'expected_recovery_rate',
                'lgd_rate',
                'is_all_account',
                'notes',
            ])
            ->orderBy('calculation_period', 'desc')
            ->orderBy('usage_type');

        if ($this->filterPeriod !== '') {
            $q->where('calculation_period', $this->filterPeriod);
        }
        if ($this->filterUsageType !== '') {
            $q->where('usage_type', $this->filterUsageType);
        }

        return $q;
    }

    public function headings(): array
    {
        return [
            'Periode Hitung',
            'Jenis Penggunaan',
            'Periode Data Mulai',
            'Periode Data Akhir',
            'Window (Tahun)',
            'Total Write-Off (Rp)',
            'Total Recovery (Rp)',
            'Expected Recovery Rate (%)',
            'Rate LGD (%)',
            'Lingkup',
            'Catatan',
        ];
    }

    public function map($row): array
    {
        $usageLabel = $row->usage_type instanceof UsageType
            ? $row->usage_type->label()
            : (string) ($row->usage_type ?? '-');

        return [
            $row->calculation_period,
            $usageLabel,
            $row->data_period_start ?? '-',
            $row->data_period_end ?? '-',
            $row->window_years ?? '-',
            (float) $row->total_writeoff_amount,
            (float) $row->total_recovery_amount,
            round((float) $row->expected_recovery_rate * 100, 6),
            round((float) $row->lgd_rate * 100, 6),
            $row->is_all_account ? 'Semua Akun' : 'Per Segmen',
            $row->notes ?? '',
        ];
    }

    public function columnWidths(): array
    {
        return [
            'A' => 16, // Periode Hitung
            'B' => 20, // Jenis Penggunaan
            'C' => 18, // Periode Data Mulai
            'D' => 18, // Periode Data Akhir
            'E' => 16, // Window (Tahun)
            'F' => 22, // Total Write-Off (Rp)
            'G' => 22, // Total Recovery (Rp)
            'H' => 26, // Expected Recovery Rate (%)
            'I' => 14, // LGD Rate (%)
            'J' => 16, // Scope
            'K' => 30, // Catatan
        ];
    }

    public function styles(Worksheet $sheet): ?array
    {
        $sheet->getStyle('A1:K1')->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF'], 'size' => 10],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => self::COLOR_HEADER]],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => '80CBC4']]],
        ]);
        $sheet->getRowDimension(1)->setRowHeight(30);
        $sheet->freezePane('A2');

        return null;
    }
}
