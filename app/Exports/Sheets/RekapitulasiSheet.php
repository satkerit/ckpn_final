<?php

declare(strict_types=1);

namespace App\Exports\Sheets;

use App\Enums\UsageType;
use App\Models\CkpnCollectiveResult;
use App\Models\CkpnIndividualResult;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Sheet 1 — Rekapitulasi menyeluruh CKPN per segmen.
 * Ref: PRD Bab 11
 */
class RekapitulasiSheet implements FromArray, ShouldAutoSize, WithStyles, WithTitle
{
    // Warna tema
    private const COLOR_HEADER_DARK = '1E3A5F'; // navy gelap — judul utama

    private const COLOR_HEADER_MID = '2D6A9F'; // biru medium — sub-header

    private const COLOR_COL_HEADER = '1565C0'; // biru kolektif

    private const COLOR_IND_HEADER = '1B5E20'; // hijau individual

    private const COLOR_ROW_EVEN = 'EEF4FB'; // biru sangat muda — row kolektif genap

    private const COLOR_IND_ROW_EVEN = 'F1F8F1'; // hijau sangat muda — row individual genap

    private const COLOR_TOTAL_ROW = 'FFF3E0'; // oranye muda — baris total

    private const COLOR_GRAND_TOTAL = 'E8F5E9'; // hijau muda — grand total

    public function __construct(
        private readonly string $filterPeriod,
        private readonly string $filterUsageType,
    ) {}

    public function title(): string
    {
        return 'Rekapitulasi';
    }

    public function array(): array
    {
        $rows = [];

        // ── Baris judul utama ────────────────────────────────────────────────
        $periodLabel = $this->filterPeriod !== '' ? $this->filterPeriod : 'Semua Periode';
        $rows[] = ['LAPORAN REKAPITULASI CKPN', '', '', '', '', '', '', ''];
        $rows[] = ['Periode: '.$periodLabel, '', '', '', '', '', '', ''];
        $rows[] = ['Tanggal Export: '.now()->format('d/m/Y H:i'), '', '', '', '', '', '', ''];
        $rows[] = ['', '', '', '', '', '', '', ''];

        // ── Bagian A: Rate PD per Segmen ─────────────────────────────────────
        $rows[] = ['A. RATE PD PER SEGMEN (CKPN KOLEKTIF)', '', '', '', '', '', '', ''];
        $rows[] = ['Segmen / Jenis Penggunaan', 'Metode PD', 'Jumlah Akun (NoA)', 'Total EAD (Rp)', 'Rata-rata Rate PD (%)', 'Total CKPN (Rp)', '', ''];

        $collectiveBySegment = $this->queryCollectiveBySegment();
        foreach ($collectiveBySegment as $seg) {
            $rows[] = [
                $seg->usage_type_label,
                $seg->pd_method_used ?? '-',
                (int) $seg->noa,
                (float) $seg->total_ead,
                round((float) $seg->avg_pd_rate * 100, 4),
                (float) $seg->total_ckpn,
                '',
                '',
            ];
        }
        $rows[] = ['', '', '', '', '', '', '', ''];

        // ── Bagian B: Rate LGD per Segmen ────────────────────────────────────
        $rows[] = ['B. RATE LGD PER SEGMEN (CKPN KOLEKTIF)', '', '', '', '', '', '', ''];
        $rows[] = ['Segmen / Jenis Penggunaan', 'Metode LGD', 'Jumlah Akun (NoA)', 'Total EAD (Rp)', 'Rata-rata Rate LGD (%)', 'Total CKPN (Rp)', '', ''];

        foreach ($collectiveBySegment as $seg) {
            $rows[] = [
                $seg->usage_type_label,
                $seg->lgd_method_used ?? '-',
                (int) $seg->noa,
                (float) $seg->total_ead,
                round((float) $seg->avg_lgd_rate * 100, 4),
                (float) $seg->total_ckpn,
                '',
                '',
            ];
        }
        $rows[] = ['', '', '', '', '', '', '', ''];

        // ── Bagian C: Ringkasan Grand Total ──────────────────────────────────
        $rows[] = ['C. RINGKASAN GRAND TOTAL', '', '', '', '', '', '', ''];
        $rows[] = ['Komponen', 'Jumlah Akun (NoA)', 'Total EAD (Rp)', 'Total CKPN (Rp)', 'Proporsi CKPN (%)', '', '', ''];

        $colTotal = $this->queryCollectiveTotal();
        $indTotal = $this->queryIndividualTotal();
        $grandNoa = $colTotal->noa + $indTotal->noa;
        $grandEad = $colTotal->total_ead + $indTotal->total_ead;
        $grandCkpn = $colTotal->total_ckpn + $indTotal->total_ckpn;

        $pctCol = $grandCkpn > 0 ? round($colTotal->total_ckpn / $grandCkpn * 100, 2) : 0;
        $pctInd = $grandCkpn > 0 ? round($indTotal->total_ckpn / $grandCkpn * 100, 2) : 0;

        $rows[] = ['CKPN Kolektif', (int) $colTotal->noa, (float) $colTotal->total_ead, (float) $colTotal->total_ckpn, $pctCol, '', '', ''];
        $rows[] = ['CKPN Individual', (int) $indTotal->noa, (float) $indTotal->total_ead, (float) $indTotal->total_ckpn, $pctInd, '', '', ''];
        $rows[] = ['GRAND TOTAL', $grandNoa, $grandEad, $grandCkpn, 100.00, '', '', ''];

        return $rows;
    }

    public function styles(Worksheet $sheet): ?array
    {
        $rows = $sheet->toArray();
        $totalRows = count($rows);

        // Judul utama — baris 1
        $sheet->mergeCells('A1:H1');
        $sheet->mergeCells('A2:H2');
        $sheet->mergeCells('A3:H3');
        $sheet->getStyle('A1:H3')->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF'], 'size' => 13],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => self::COLOR_HEADER_DARK]],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
        ]);
        $sheet->getRowDimension(1)->setRowHeight(28);
        $sheet->getRowDimension(2)->setRowHeight(20);
        $sheet->getRowDimension(3)->setRowHeight(18);

        // Scan baris per baris untuk styling dinamis
        $sectionAHeaderRow = null;
        $sectionBHeaderRow = null;
        $sectionCHeaderRow = null;
        $colHeaderRows = [];
        $indHeaderRows = [];
        $colDataRows = [];
        $indDataRows = [];
        $totalRow = null;
        $grandTotalRow = null;

        $inSectionA = false;
        $inSectionB = false;
        $inSectionC = false;
        $colDataCount = 0;
        $indDataCount = 0;

        foreach ($rows as $i => $row) {
            $rowNum = $i + 1; // baris 1-based
            $cell = trim((string) ($row[0] ?? ''));

            if (str_starts_with($cell, 'A. RATE PD')) {
                $sectionAHeaderRow = $rowNum;
                $inSectionA = true;
                $inSectionB = false;
                $inSectionC = false;
            } elseif (str_starts_with($cell, 'B. RATE LGD')) {
                $sectionBHeaderRow = $rowNum;
                $inSectionA = false;
                $inSectionB = true;
                $inSectionC = false;
            } elseif (str_starts_with($cell, 'C. RINGKASAN')) {
                $sectionCHeaderRow = $rowNum;
                $inSectionA = false;
                $inSectionB = false;
                $inSectionC = true;
            } elseif ($cell === 'Segmen / Jenis Penggunaan') {
                if ($inSectionA) {
                    $colHeaderRows[] = $rowNum;
                } elseif ($inSectionB) {
                    $indHeaderRows[] = $rowNum;
                }
            } elseif ($cell === 'Komponen') {
                $indHeaderRows[] = $rowNum; // reuse warna hijau untuk header section C
            } elseif ($cell === 'GRAND TOTAL') {
                $grandTotalRow = $rowNum;
            } elseif ($cell === 'CKPN Kolektif' || $cell === 'CKPN Individual') {
                $totalRow = $rowNum; // baris total section C
            } elseif ($inSectionA && $cell !== '' && $rowNum > 5) {
                $colDataRows[] = $rowNum;
                $colDataCount++;
            } elseif ($inSectionB && $cell !== '' && $rowNum > 5) {
                $indDataRows[] = $rowNum;
                $indDataCount++;
            }
        }

        // Styling section header A & B
        foreach (array_filter([$sectionAHeaderRow, $sectionBHeaderRow]) as $r) {
            $sheet->mergeCells("A{$r}:H{$r}");
            $sheet->getStyle("A{$r}:H{$r}")->applyFromArray([
                'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF'], 'size' => 11],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => self::COLOR_COL_HEADER]],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT],
            ]);
            $sheet->getRowDimension($r)->setRowHeight(22);
        }

        // Styling section header C
        if ($sectionCHeaderRow) {
            $sheet->mergeCells("A{$sectionCHeaderRow}:H{$sectionCHeaderRow}");
            $sheet->getStyle("A{$sectionCHeaderRow}:H{$sectionCHeaderRow}")->applyFromArray([
                'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF'], 'size' => 11],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => self::COLOR_IND_HEADER]],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT],
            ]);
            $sheet->getRowDimension($sectionCHeaderRow)->setRowHeight(22);
        }

        // Column header (biru kolektif)
        foreach ($colHeaderRows as $r) {
            $sheet->getStyle("A{$r}:F{$r}")->applyFromArray([
                'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1976D2']],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
                'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'BBDEFB']]],
            ]);
        }

        // Column header (hijau individual)
        foreach ($indHeaderRows as $r) {
            $sheet->getStyle("A{$r}:F{$r}")->applyFromArray([
                'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '388E3C']],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
                'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'C8E6C9']]],
            ]);
        }

        // Data rows kolektif (zebra)
        foreach ($colDataRows as $idx => $r) {
            $bg = ($idx % 2 === 0) ? 'FFFFFF' : self::COLOR_ROW_EVEN;
            $sheet->getStyle("A{$r}:F{$r}")->applyFromArray([
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $bg]],
                'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_HAIR, 'color' => ['rgb' => 'CFD8DC']]],
            ]);
            // Format angka kolom D (EAD), F (CKPN)
            $sheet->getStyle("D{$r}")->getNumberFormat()->setFormatCode('#,##0');
            $sheet->getStyle("F{$r}")->getNumberFormat()->setFormatCode('#,##0');
            $sheet->getStyle("E{$r}")->getNumberFormat()->setFormatCode('0.0000"%"');
        }

        // Data rows individual (zebra)
        foreach ($indDataRows as $idx => $r) {
            $bg = ($idx % 2 === 0) ? 'FFFFFF' : self::COLOR_IND_ROW_EVEN;
            $sheet->getStyle("A{$r}:F{$r}")->applyFromArray([
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $bg]],
                'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_HAIR, 'color' => ['rgb' => 'CFD8DC']]],
            ]);
            $sheet->getStyle("D{$r}")->getNumberFormat()->setFormatCode('#,##0');
            $sheet->getStyle("F{$r}")->getNumberFormat()->setFormatCode('#,##0');
            $sheet->getStyle("E{$r}")->getNumberFormat()->setFormatCode('0.0000"%"');
        }

        // Total rows section C (oranye muda)
        if ($totalRow) {
            $sheet->getStyle("A{$totalRow}:E{$totalRow}")->applyFromArray([
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => self::COLOR_TOTAL_ROW]],
                'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'FFB74D']]],
            ]);
            $sheet->getStyle("C{$totalRow}")->getNumberFormat()->setFormatCode('#,##0');
            $sheet->getStyle("D{$totalRow}")->getNumberFormat()->setFormatCode('#,##0');
            // Satu baris di bawah totalRow juga (CKPN Individual)
            $r2 = $totalRow + 1;
            $sheet->getStyle("A{$r2}:E{$r2}")->applyFromArray([
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => self::COLOR_TOTAL_ROW]],
                'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'FFB74D']]],
            ]);
            $sheet->getStyle("C{$r2}")->getNumberFormat()->setFormatCode('#,##0');
            $sheet->getStyle("D{$r2}")->getNumberFormat()->setFormatCode('#,##0');
        }

        // Grand total (hijau muda, bold)
        if ($grandTotalRow) {
            $sheet->getStyle("A{$grandTotalRow}:E{$grandTotalRow}")->applyFromArray([
                'font' => ['bold' => true],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => self::COLOR_GRAND_TOTAL]],
                'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_MEDIUM, 'color' => ['rgb' => '43A047']]],
            ]);
            $sheet->getStyle("C{$grandTotalRow}")->getNumberFormat()->setFormatCode('#,##0');
            $sheet->getStyle("D{$grandTotalRow}")->getNumberFormat()->setFormatCode('#,##0');
        }

        return null;
    }

    // ── Query helpers ────────────────────────────────────────────────────────

    private function queryCollectiveBySegment(): Collection
    {
        $q = CkpnCollectiveResult::query()
            ->select([
                'usage_type',
                'pd_method_used',
                'lgd_method_used',
                DB::raw('COUNT(DISTINCT financing_account_id) as noa'),
                DB::raw('SUM(ead) as total_ead'),
                DB::raw('AVG(pd_rate) as avg_pd_rate'),
                DB::raw('AVG(lgd_rate) as avg_lgd_rate'),
                DB::raw('SUM(ckpn_amount) as total_ckpn'),
            ])
            ->groupBy('usage_type', 'pd_method_used', 'lgd_method_used');

        if ($this->filterPeriod !== '') {
            $q->where('calculation_period', $this->filterPeriod);
        }
        if ($this->filterUsageType !== '') {
            $q->where('usage_type', $this->filterUsageType);
        }

        return $q->get()->map(function ($row) {
            $row->usage_type_label = $row->usage_type instanceof UsageType
                ? $row->usage_type->label()
                : (string) ($row->usage_type ?? '-');

            return $row;
        });
    }

    private function queryCollectiveTotal(): object
    {
        $q = CkpnCollectiveResult::query()
            ->select([
                DB::raw('COUNT(DISTINCT financing_account_id) as noa'),
                DB::raw('SUM(ead) as total_ead'),
                DB::raw('SUM(ckpn_amount) as total_ckpn'),
            ]);

        if ($this->filterPeriod !== '') {
            $q->where('calculation_period', $this->filterPeriod);
        }

        $row = $q->first();

        return (object) [
            'noa' => (int) ($row->noa ?? 0),
            'total_ead' => (float) ($row->total_ead ?? 0),
            'total_ckpn' => (float) ($row->total_ckpn ?? 0),
        ];
    }

    private function queryIndividualTotal(): object
    {
        $q = CkpnIndividualResult::query()
            ->select([
                DB::raw('COUNT(DISTINCT financing_account_id) as noa'),
                DB::raw('SUM(outstanding_balance) as total_ead'),
                DB::raw('SUM(ckpn_amount) as total_ckpn'),
            ]);

        if ($this->filterPeriod !== '') {
            $q->where('calculation_period', $this->filterPeriod);
        }

        $row = $q->first();

        return (object) [
            'noa' => (int) ($row->noa ?? 0),
            'total_ead' => (float) ($row->total_ead ?? 0),
            'total_ckpn' => (float) ($row->total_ckpn ?? 0),
        ];
    }
}
