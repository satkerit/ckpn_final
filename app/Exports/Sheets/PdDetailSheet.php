<?php

declare(strict_types=1);

namespace App\Exports\Sheets;

use App\Enums\UsageType;
use App\Models\PdMigrationResult;
use App\Models\PdNetflowResult;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Sheet 4 — Pivot detail PD per segmen.
 * Netflow: pivot bucket-based (from_bucket → pd_rate).
 * Migration: pivot quality-grade-based (from_quality_grade → pd_rate).
 * Ref: PRD Bab 7 & 8
 */
class PdDetailSheet implements FromArray, ShouldAutoSize, WithStyles, WithTitle
{
    private const COLOR_TITLE = '1E3A5F';

    private const COLOR_NETFLOW_HDR = '1565C0';

    private const COLOR_MIGR_HDR = '6A1B9A'; // ungu — migration

    private const COLOR_COL_HDR = '1976D2';

    private const COLOR_COL_MIGR = '7B1FA2';

    private const COLOR_EVEN_NF = 'EEF4FB';

    private const COLOR_EVEN_MG = 'F3E5F5';

    public function __construct(
        private readonly string $filterPeriod,
        private readonly string $filterUsageType,
    ) {}

    public function title(): string
    {
        return 'Detail PD';
    }

    public function array(): array
    {
        $rows = [];

        $periodLabel = $this->filterPeriod !== '' ? $this->filterPeriod : 'Semua Periode';
        $rows[] = ['DETAIL RATE PD PER SEGMEN', '', '', '', '', ''];
        $rows[] = ['Periode: '.$periodLabel, '', '', '', '', ''];
        $rows[] = ['', '', '', '', '', ''];

        // ── Seksi A: Netflow ─────────────────────────────────────────────────
        $rows[] = ['A. METODE NETFLOW (Rate PD per Bucket)', '', '', '', '', ''];
        $rows[] = ['Jenis Penggunaan', 'Bucket Asal', 'Periode Hitung', 'Periode Data Mulai', 'Periode Data Akhir', 'Window (Bulan)', 'Rate PD (%)'];

        $netflowRows = $this->queryNetflow();
        if ($netflowRows->isEmpty()) {
            $rows[] = ['(Tidak ada data Netflow untuk filter ini)', '', '', '', '', '', ''];
        } else {
            foreach ($netflowRows as $row) {
                $rows[] = [
                    $row->usage_type_label,
                    $row->bucket_label ?? $row->from_bucket_id ?? '-',
                    $row->calculation_period,
                    $row->data_period_start ?? '-',
                    $row->data_period_end ?? '-',
                    $row->window_months ?? '-',
                    round((float) $row->pd_rate * 100, 6),
                ];
            }
        }

        $rows[] = ['', '', '', '', '', '', ''];

        // ── Seksi B: Migration ────────────────────────────────────────────────
        $rows[] = ['B. METODE MIGRATION (Rate PD per Quality Grade)', '', '', '', '', ''];
        $rows[] = ['Jenis Penggunaan', 'Grade Asal', 'Collectibility', 'Periode Hitung', 'Periode Data Mulai', 'Periode Data Akhir', 'Jumlah Cohort', 'Rate PD (%)'];

        $migrationRows = $this->queryMigration();
        if ($migrationRows->isEmpty()) {
            $rows[] = ['(Tidak ada data Migration untuk filter ini)', '', '', '', '', '', '', ''];
        } else {
            foreach ($migrationRows as $row) {
                $rows[] = [
                    $row->usage_type_label,
                    $row->grade_label ?? $row->from_quality_grade_id ?? '-',
                    $row->collectibility_number ?? '-',
                    $row->calculation_period,
                    $row->data_period_start ?? '-',
                    $row->data_period_end ?? '-',
                    (int) ($row->cohort_count ?? 0),
                    round((float) $row->pd_rate * 100, 6),
                ];
            }
        }

        return $rows;
    }

    public function styles(Worksheet $sheet): ?array
    {
        $rows = $sheet->toArray();
        $total = count($rows);

        // Judul utama
        $sheet->mergeCells('A1:G1');
        $sheet->mergeCells('A2:G2');
        $sheet->getStyle('A1:G2')->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF'], 'size' => 13],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => self::COLOR_TITLE]],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);
        $sheet->getRowDimension(1)->setRowHeight(26);

        $inNetflow = false;
        $inMigration = false;
        $nfDataRows = [];
        $mgDataRows = [];
        $nfColHdr = null;
        $mgColHdr = null;
        $nfSecHdr = null;
        $mgSecHdr = null;

        foreach ($rows as $i => $row) {
            $r = $i + 1;
            $cell = trim((string) ($row[0] ?? ''));

            if (str_starts_with($cell, 'A. METODE NETFLOW')) {
                $nfSecHdr = $r;
                $inNetflow = true;
                $inMigration = false;
            } elseif (str_starts_with($cell, 'B. METODE MIGRATION')) {
                $mgSecHdr = $r;
                $inMigration = true;
                $inNetflow = false;
            } elseif ($cell === 'Jenis Penggunaan' && $inNetflow) {
                $nfColHdr = $r;
            } elseif ($cell === 'Jenis Penggunaan' && $inMigration) {
                $mgColHdr = $r;
            } elseif ($inNetflow && $cell !== '' && $nfColHdr && $r > $nfColHdr) {
                $nfDataRows[] = $r;
            } elseif ($inMigration && $cell !== '' && $mgColHdr && $r > $mgColHdr) {
                $mgDataRows[] = $r;
            }
        }

        // Section header netflow
        if ($nfSecHdr) {
            $sheet->mergeCells("A{$nfSecHdr}:G{$nfSecHdr}");
            $sheet->getStyle("A{$nfSecHdr}:G{$nfSecHdr}")->applyFromArray([
                'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF'], 'size' => 11],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => self::COLOR_NETFLOW_HDR]],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT],
            ]);
            $sheet->getRowDimension($nfSecHdr)->setRowHeight(22);
        }

        // Section header migration
        if ($mgSecHdr) {
            $sheet->mergeCells("A{$mgSecHdr}:H{$mgSecHdr}");
            $sheet->getStyle("A{$mgSecHdr}:H{$mgSecHdr}")->applyFromArray([
                'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF'], 'size' => 11],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => self::COLOR_MIGR_HDR]],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT],
            ]);
            $sheet->getRowDimension($mgSecHdr)->setRowHeight(22);
        }

        // Column header netflow (7 kolom)
        if ($nfColHdr) {
            $sheet->getStyle("A{$nfColHdr}:G{$nfColHdr}")->applyFromArray([
                'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => self::COLOR_COL_HDR]],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'wrapText' => true],
                'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'BBDEFB']]],
            ]);
            $sheet->getRowDimension($nfColHdr)->setRowHeight(28);
        }

        // Column header migration (8 kolom)
        if ($mgColHdr) {
            $sheet->getStyle("A{$mgColHdr}:H{$mgColHdr}")->applyFromArray([
                'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => self::COLOR_COL_MIGR]],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'wrapText' => true],
                'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'E1BEE7']]],
            ]);
            $sheet->getRowDimension($mgColHdr)->setRowHeight(28);
        }

        // Data rows netflow (zebra biru)
        foreach ($nfDataRows as $idx => $r) {
            $bg = ($idx % 2 === 0) ? 'FFFFFF' : self::COLOR_EVEN_NF;
            $sheet->getStyle("A{$r}:G{$r}")->applyFromArray([
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $bg]],
                'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_HAIR, 'color' => ['rgb' => 'CFD8DC']]],
            ]);
            $sheet->getStyle("G{$r}")->getNumberFormat()->setFormatCode('0.000000"%"');
            $sheet->getStyle("C{$r}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle("D{$r}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle("E{$r}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        }

        // Data rows migration (zebra ungu)
        foreach ($mgDataRows as $idx => $r) {
            $bg = ($idx % 2 === 0) ? 'FFFFFF' : self::COLOR_EVEN_MG;
            $sheet->getStyle("A{$r}:H{$r}")->applyFromArray([
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $bg]],
                'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_HAIR, 'color' => ['rgb' => 'CFD8DC']]],
            ]);
            $sheet->getStyle("H{$r}")->getNumberFormat()->setFormatCode('0.000000"%"');
            $sheet->getStyle("D{$r}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle("E{$r}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle("F{$r}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle("G{$r}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        }

        $sheet->freezePane('A3');

        return null;
    }

    // ── Query helpers ─────────────────────────────────────────────────────────

    private function queryNetflow(): Collection
    {
        $q = PdNetflowResult::query()
            ->select([
                'pd_netflow_result.usage_type',
                'pd_netflow_result.from_bucket_id',
                'pd_netflow_result.calculation_period',
                'pd_netflow_result.pd_rate',
                'pd_netflow_result.data_period_start',
                'pd_netflow_result.data_period_end',
                'pd_netflow_result.window_months',
                'buckets.label as bucket_label',
            ])
            ->leftJoin('buckets', 'buckets.id', '=', 'pd_netflow_result.from_bucket_id')
            ->orderBy('pd_netflow_result.calculation_period', 'desc')
            ->orderBy('buckets.bucket_order');

        if ($this->filterPeriod !== '') {
            $q->where('pd_netflow_result.calculation_period', $this->filterPeriod);
        }
        if ($this->filterUsageType !== '') {
            $q->where('pd_netflow_result.usage_type', $this->filterUsageType);
        }

        return $q->get()->map(fn ($row) => $this->resolveUsageTypeLabel($row));
    }

    private function queryMigration(): Collection
    {
        $q = PdMigrationResult::query()
            ->select([
                'pd_migration_result.usage_type',
                'pd_migration_result.from_quality_grade_id',
                'pd_migration_result.calculation_period',
                'pd_migration_result.pd_rate',
                'pd_migration_result.cohort_count',
                'pd_migration_result.data_period_start',
                'pd_migration_result.data_period_end',
                'quality_grades.label as grade_label',
                'quality_grades.collectibility_number',
            ])
            ->leftJoin('quality_grades', 'quality_grades.id', '=', 'pd_migration_result.from_quality_grade_id')
            ->orderBy('pd_migration_result.calculation_period', 'desc')
            ->orderBy('quality_grades.collectibility_number');

        if ($this->filterPeriod !== '') {
            $q->where('pd_migration_result.calculation_period', $this->filterPeriod);
        }
        if ($this->filterUsageType !== '') {
            $q->where('pd_migration_result.usage_type', $this->filterUsageType);
        }

        return $q->get()->map(fn ($row) => $this->resolveUsageTypeLabel($row));
    }

    private function resolveUsageTypeLabel(object $row): object
    {
        $row->usage_type_label = $row->usage_type instanceof UsageType
            ? $row->usage_type->label()
            : (string) ($row->usage_type ?? '-');

        return $row;
    }
}
