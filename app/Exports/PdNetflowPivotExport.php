<?php

declare(strict_types=1);

namespace App\Exports;

use App\Domain\Ckpn\Pd\Netflow\PdNetflowBaseline;
use App\Models\Bucket;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\Export;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class PdNetflowPivotExport implements Export, WithMultipleSheets
{
    /** @param array<string, array<string, mixed>> $datasets */
    public function __construct(private array $datasets) {}

    public function sheets(): array
    {
        $sheets = [];
        foreach ($this->datasets as $title => $dataset) {
            $sheets[] = new PdNetflowDetailSheet(
                outstandingData: $dataset['outstanding'],
                transitionData: $dataset['transition'],
                compoundData: $dataset['compound'],
                compoundAvg: $dataset['compound_avg'],
                projectionData: $dataset['projection'],
                outstandingPeriods: $dataset['outstanding_periods'],
                transitionPeriods: $dataset['transition_periods'],
                compoundPeriods: $dataset['compound_periods'],
                projectionPeriods: $dataset['projection_periods'],
                buckets: $dataset['buckets'],
                title: $title,
            );
        }

        return $sheets;
    }
}

/**
 * Sheet detail PD Netflow — 3 section dalam 1 sheet dengan separator 2 baris.
 * Warna header konsisten: section title = biru tua, column heading = biru medium, proyeksi = amber tua.
 * Ref: PRD Bab 7
 */
class PdNetflowDetailSheet implements FromArray, WithColumnWidths, WithStyles, WithTitle
{
    // Warna konsisten untuk seluruh sheet
    private const COLOR_SECTION_BG = 'FF1E3A8A'; // biru tua — judul section

    private const COLOR_SECTION_FG = 'FFFFFFFF';

    private const COLOR_HEADING_BG = 'FF1D4ED8'; // biru medium — header kolom

    private const COLOR_HEADING_FG = 'FFFFFFFF';

    private const COLOR_PROJ_BG = 'FFB45309'; // amber tua — kolom proyeksi

    private const COLOR_PROJ_FG = 'FFFFFFFF';

    private const COLOR_DATA_ALT = 'FFF8FAFF'; // biru sangat muda — zebra strip

    private const COLOR_BORDER = 'FFD1D5DB'; // abu terang — border data

    /** Baris yang dihasilkan oleh array() — dicatat saat build agar styles() akurat. */
    private array $rowMeta = [];

    public function __construct(
        private array $outstandingData,
        private array $transitionData,
        private array $compoundData,
        private array $compoundAvg,
        private array $projectionData,
        private array $outstandingPeriods,
        private array $transitionPeriods,
        private array $compoundPeriods,
        private array $projectionPeriods,
        private array $buckets,
        private string $title,
    ) {}

    public function title(): string
    {
        return $this->title;
    }

    public function array(): array
    {
        $rows = [];
        $meta = [];
        $cursor = 1; // nomor baris aktual di sheet (1-based)
        $buckets = array_values($this->buckets);
        $nBuckets = count($buckets);

        // ── SECTION 1: Outstanding per Bucket ─────────────────────────────
        $rows[] = ['1. OUTSTANDING PER BUCKET PER PERIODE (Rp — rupiah penuh)'];
        $meta[] = ['type' => 'section', 'row' => $cursor++];

        $rows[] = array_merge(['Bucket'], $this->outstandingPeriods);
        $meta[] = ['type' => 'heading', 'row' => $cursor++, 'proj_from' => null];

        foreach ($buckets as $idx => $bucket) {
            $row = [$bucket['code'].' - '.$bucket['label']];
            foreach ($this->outstandingPeriods as $period) {
                $val = $this->outstandingData[$bucket['id']][$period] ?? 0;
                $row[] = $val > 0 ? (int) round($val) : 0;
            }
            $rows[] = $row;
            $meta[] = ['type' => $idx % 2 === 0 ? 'data' : 'data_alt', 'row' => $cursor++];
        }

        // 2 baris kosong sebagai pemisah antar section
        $rows[] = [];
        $cursor++;
        $rows[] = [];
        $cursor++;

        // ── SECTION 2: Transition Rate + Proyeksi ─────────────────────────
        $rows[] = ['2. PERSENTASE PERGERAKAN BUCKET (TRANSITION RATE)'];
        $meta[] = ['type' => 'section', 'row' => $cursor++];

        $projStart = count($this->transitionPeriods) + 1; // kolom ke-N proyeksi mulai (1-based, A=kolom bucket)
        $rows[] = array_merge(
            ['Perpindahan'],
            $this->transitionPeriods,
            array_map(static fn (string $p): string => $p.' (Proyeksi)', $this->projectionPeriods),
        );
        $meta[] = ['type' => 'heading', 'row' => $cursor++, 'proj_from' => $projStart + 1];

        for ($i = 0; $i < $nBuckets - 1; $i++) {
            $from = $buckets[$i];
            $to = $buckets[$i + 1];
            $row = [$from['code'].' → '.$to['code']];
            foreach ($this->transitionPeriods as $period) {
                $val = $this->transitionData[$from['id']][$period] ?? null;
                $row[] = $val !== null ? round(min(1, max(0, $val)) * 100, 4).'%' : '-';
            }
            foreach ($this->projectionPeriods as $period) {
                $val = $this->projectionData[$from['id']][$period] ?? null;
                $row[] = $val !== null ? round(min(1, max(0, $val)) * 100, 4).'%' : '-';
            }
            $rows[] = $row;
            $meta[] = ['type' => $i % 2 === 0 ? 'data' : 'data_alt', 'row' => $cursor++];
        }

        $rows[] = [];
        $cursor++;
        $rows[] = [];
        $cursor++;

        // ── SECTION 3: Compound Flow to Loss ──────────────────────────────
        $rows[] = ['3. COMPOUND FLOW TO LOSS & RATA-RATA PD PER BUCKET'];
        $meta[] = ['type' => 'section', 'row' => $cursor++];

        $rows[] = array_merge(['Bucket'], $this->compoundPeriods, ['Rata-rata PD (%)']);
        $meta[] = ['type' => 'heading', 'row' => $cursor++, 'proj_from' => null];

        for ($i = 0; $i < $nBuckets - 1; $i++) {
            $bucket = $buckets[$i];
            $row = [$bucket['code'].' - '.$bucket['label']];
            foreach ($this->compoundPeriods as $period) {
                $val = $this->compoundData[$bucket['id']][$period] ?? 0;
                $row[] = $val > 0 ? round($val * 100, 4).'%' : '-';
            }
            $avg = $this->compoundAvg[$bucket['id']] ?? 0;
            $row[] = $avg > 0 ? round($avg * 100, 4).'%' : '-';
            $rows[] = $row;
            $meta[] = ['type' => $i % 2 === 0 ? 'data' : 'data_alt', 'row' => $cursor++];
        }

        $this->rowMeta = $meta;

        return $rows;
    }

    public function styles(Worksheet $sheet): array
    {
        // Pastikan array() sudah dipanggil (dipanggil sebelum styles() oleh maatwebsite)
        if ($this->rowMeta === []) {
            $this->array();
        }

        $styles = [];

        foreach ($this->rowMeta as $meta) {
            $r = $meta['row'];

            switch ($meta['type']) {
                case 'section':
                    $styles[$r] = [
                        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => self::COLOR_SECTION_BG]],
                        'font' => ['bold' => true, 'size' => 11, 'color' => ['argb' => self::COLOR_SECTION_FG]],
                        'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT, 'vertical' => Alignment::VERTICAL_CENTER],
                    ];
                    $sheet->getRowDimension($r)->setRowHeight(22);
                    break;

                case 'heading':
                    $styles[$r] = [
                        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => self::COLOR_HEADING_BG]],
                        'font' => ['bold' => true, 'size' => 10, 'color' => ['argb' => self::COLOR_HEADING_FG]],
                        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
                    ];
                    $sheet->getRowDimension($r)->setRowHeight(20);
                    // Warnai kolom proyeksi pada heading ini
                    if (! empty($meta['proj_from'])) {
                        $highestCol = $sheet->getHighestColumn($r);
                        for ($col = $meta['proj_from']; $col <= Coordinate::columnIndexFromString($highestCol); $col++) {
                            $colLetter = Coordinate::stringFromColumnIndex($col);
                            $sheet->getStyle("{$colLetter}{$r}")->applyFromArray([
                                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => self::COLOR_PROJ_BG]],
                                'font' => ['bold' => true, 'color' => ['argb' => self::COLOR_PROJ_FG]],
                            ]);
                        }
                    }
                    break;

                case 'data_alt':
                    $styles[$r] = [
                        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => self::COLOR_DATA_ALT]],
                    ];
                    break;
            }
        }

        return $styles;
    }

    public function columnWidths(): array
    {
        // Kolom A lebar untuk label bucket; kolom periode otomatis 12
        $widths = ['A' => 30];
        $allPeriods = array_merge(
            $this->outstandingPeriods,
            $this->transitionPeriods,
            $this->compoundPeriods,
        );
        $colIdx = 2; // B = 2
        foreach (array_unique($allPeriods) as $_) {
            $widths[Coordinate::stringFromColumnIndex($colIdx)] = 14;
            $colIdx++;
        }

        return $widths;
    }
}

/**
 * Sheet 2: Daftar Debitur — stream langsung dari DB via FromQuery + WithChunkReading.
 * Bucket label di-resolve via SQL CASE WHEN agar tidak ada PHP loop per baris.
 * Ref: PRD Bab 7
 */
class DebtorListSheet implements FromQuery, WithChunkReading, WithColumnWidths, WithHeadings, WithMapping, WithStyles, WithTitle
{
    /** @param string[] $outstandingPeriods */
    public function __construct(
        private array $outstandingPeriods,
        private ?string $usageType,
    ) {}

    public function title(): string
    {
        return 'Daftar Debitur';
    }

    public function chunkSize(): int
    {
        return 1000;
    }

    public function query(): Builder
    {
        // Bangun CASE WHEN bucket label langsung di SQL — tidak ada PHP loop per baris
        $buckets = Bucket::orderBy('bucket_order')->get();
        $bucketCase = 'CASE';
        foreach ($buckets as $b) {
            if ($b->min_days_overdue === null) {
                $bucketCase .= ' WHEN fap.tgkhari = 0 THEN '.DB::getPdo()->quote($b->label);
            } else {
                $max = $b->max_days_overdue !== null
                    ? "AND fap.tgkhari <= {$b->max_days_overdue}"
                    : '';
                $bucketCase .= " WHEN fap.tgkhari >= {$b->min_days_overdue} {$max} THEN ".DB::getPdo()->quote($b->label);
            }
        }
        $bucketCase .= " ELSE '-' END";

        $query = PdNetflowBaseline::apply(
            DB::table('financing_account_periods as fap')
                ->join('financing_accounts as fa', 'fa.id', '=', 'fap.financing_account_id')
                ->whereIn('fap.period', $this->outstandingPeriods)
                ->when($this->usageType !== null, fn ($q) => $q->where('fa.usage_type', $this->usageType))
        )->selectRaw("
            fa.account_number,
            fa.customer_name,
            fa.product_code,
            fa.office_code,
            fa.akad_code,
            fap.origination_date,
            fap.maturity_date,
            fap.period,
            fap.outstanding_balance,
            fap.tgkhari,
            ({$bucketCase}) AS bucket_label,
            fap.collectibility,
            fap.financing_status,
            CASE WHEN fap.writeoff_status = 'W' THEN 'WO' ELSE 'Non-WO' END AS tipe_wo
        ")
            ->orderBy('fap.period')
            ->orderBy('fa.account_number');

        return $query;
    }

    /** @param \stdClass $row */
    public function map($row): array
    {
        return [
            $row->account_number ?? '-',
            $row->customer_name ?? '-',
            $row->product_code ?? '-',
            $row->office_code ?? '-',
            $row->akad_code ?? '-',
            $row->origination_date ?? '-',
            $row->maturity_date ?? '-',
            $row->period ?? '-',
            (float) ($row->outstanding_balance ?? 0),
            (int) ($row->tgkhari ?? 0),
            $row->bucket_label ?? '-',
            $row->collectibility ?? '-',
            $row->financing_status ?? '-',
            $row->tipe_wo ?? '-',
        ];
    }

    public function headings(): array
    {
        return [
            'No. Rekening',
            'Nama Nasabah',
            'Kode Produk',
            'Kode Kantor',
            'Kode Akad',
            'Tanggal Awal Pembiayaan',
            'Tanggal Jatuh Tempo',
            'Periode',
            'Outstanding (Rp)',
            'Hari Tunggakan',
            'Bucket',
            'Kolektibilitas',
            'Status Pembiayaan',
            'Tipe',
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => [
                'font' => ['bold' => true, 'color' => ['argb' => 'FFFFFFFF']],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FF1E3A5F']],
            ],
        ];
    }

    public function columnWidths(): array
    {
        return [
            'A' => 20,
            'B' => 30,
            'C' => 14,
            'D' => 14,
            'E' => 12,
            'F' => 16,
            'G' => 16,
            'H' => 10,
            'I' => 18,
            'J' => 14,
            'K' => 16,
            'L' => 14,
            'M' => 18,
            'N' => 12,
        ];
    }
}
