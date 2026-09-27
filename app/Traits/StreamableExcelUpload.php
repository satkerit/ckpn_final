<?php

declare(strict_types=1);

namespace App\Traits;

use Carbon\Carbon;
use OpenSpout\Common\Entity\Cell;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Reader\CSV\Reader as CsvReader;
use OpenSpout\Reader\ReaderInterface;
use OpenSpout\Reader\XLSX\Reader as XlsxReader;

/**
 * Trait untuk streaming read file Excel (.xlsx, .csv) dengan OpenSpout.
 *
 * KEUNGGULAN DIBANDINGKAN PHPSPREADSHEET:
 *   - Memory konstan ~30-50MB berapapun ukuran file (streaming murni)
 *   - Tidak load seluruh worksheet ke RAM
 *   - Performa 2-5x lebih cepat untuk file besar (>100k rows)
 *
 * Ref: AGENTS.md Bab 11 - Clean Code & Reusability
 */
trait StreamableExcelUpload
{
    /**
     * Buat reader OpenSpout sesuai ekstensi file (.xlsx atau .csv).
     *
     * @param  string  $filePath  Path ke file data
     * @return ReaderInterface Reader instance yang siap di-stream
     */
    protected function createReader(string $filePath): ReaderInterface
    {
        $extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));

        $reader = match ($extension) {
            'csv' => new CsvReader,
            'xlsx' => new XlsxReader,
            default => throw new \InvalidArgumentException("Format file '.{$extension}' tidak didukung oleh OpenSpout reader. Gunakan format .xlsx atau .csv."),
        };

        $reader->open($filePath);

        return $reader;
    }

    /**
     * Extract heading row (baris pertama) sebagai map: heading -> column index.
     *
     * @param  ReaderInterface  $reader  Reader yang sudah di-open
     * @return array<string, int> Map heading lowercased => column index (0-based)
     */
    protected function extractHeadings(ReaderInterface $reader): array
    {
        $headings = [];

        foreach ($reader->getSheetIterator() as $sheet) {
            foreach ($sheet->getRowIterator() as $rowIndex => $row) {
                if ($rowIndex === 1) {
                    /** @var Row $row */
                    $colIndex = 0;
                    foreach ($row->getCells() as $cell) {
                        /** @var Cell $cell */
                        $value = strtolower(trim((string) $cell->getValue()));
                        if ($value !== '') {
                            $headings[$value] = $colIndex;
                        }
                        $colIndex++;
                    }
                    break 2; // Hanya ambil baris pertama dari sheet pertama
                }
            }
        }

        return $headings;
    }

    /**
     * Stream baris data (mulai baris 2) sebagai generator.
     *
     * @param  ReaderInterface  $reader  Reader yang sudah di-open
     * @return \Generator<int, array<int, mixed>> Generator: rowNum => rowData
     */
    protected function streamRows(ReaderInterface $reader): \Generator
    {
        foreach ($reader->getSheetIterator() as $sheet) {
            foreach ($sheet->getRowIterator() as $rowIndex => $row) {
                // Skip heading row
                if ($rowIndex === 1) {
                    continue;
                }

                $rowData = [];
                /** @var Row $row */
                $colIndex = 0;
                foreach ($row->getCells() as $cell) {
                    /** @var Cell $cell */
                    $rowData[$colIndex] = $cell->getValue();
                    $colIndex++;
                }

                yield $rowIndex => $rowData;
            }

            // Hanya proses sheet pertama
            break;
        }
    }

    /**
     * Tutup reader dan bebaskan resource.
     */
    protected function closeReader(ReaderInterface $reader): void
    {
        $reader->close();
    }

    /**
     * Resolve index kolom berdasarkan beberapa alias yang mungkin.
     *
     * @param  array<string, int>  $headingMap  Map heading => index
     * @param  array<int, string>  $aliases  Daftar kemungkinan nama kolom
     * @return int Index kolom (0-based), atau -1 jika tidak ditemukan
     */
    protected function resolveColIndex(array $headingMap, array $aliases): int
    {
        foreach ($aliases as $alias) {
            $key = strtolower(trim($alias));
            if (isset($headingMap[$key])) {
                return $headingMap[$key];
            }
        }

        return -1;
    }

    /**
     * Ambil nilai dari rowData berdasarkan index, dengan default.
     *
     * @param  array<int, mixed>  $rowData  Data baris dari streamRows()
     * @param  int  $colIndex  Index kolom (dari resolveColIndex)
     * @param  mixed  $default  Nilai default jika kolom tidak ada
     */
    protected function getCellValue(array $rowData, int $colIndex, mixed $default = null): mixed
    {
        return $rowData[$colIndex] ?? $default;
    }

    /**
     * Parse nilai menjadi string tanggal format 'Y-m-d', atau null jika tidak valid.
     *
     * Format yang didukung:
     *   - String 8 digit (Ymd) mis. "20241231"
     *   - Numeric Excel serial date (1-99999)
     *   - String format lain ("31/12/2024", "2024-12-31")
     *
     * @param  mixed  $value  Nilai sel mentah
     * @return string|null Tanggal format 'Y-m-d' atau null
     */
    protected function parseDate(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        $str = trim((string) $value);
        $date = null;

        // Format Ymd (8 digit)
        if (preg_match('/^\d{8}$/', $str)) {
            $date = preg_match('/^(19|20)\d{2}(0[1-9]|1[0-2])(0[1-9]|[12]\d|3[01])$/', $str)
                ? Carbon::createFromFormat('Ymd', $str)?->toDateString()
                : null;
        }
        // Excel serial date (numeric 1-99999)
        elseif (is_numeric($value) && $value > 0 && $value < 100000) {
            // Excel serial date: hari ke-X sejak 1900-01-01 (dengan bug 1900 leap year)
            // Konversi: Unix timestamp = (serial - 25569) * 86400
            $date = Carbon::createFromTimestamp(((int) $value - 25569) * 86400)?->toDateString();
        }
        // Format lain: coba parse via Carbon
        else {
            try {
                $date = Carbon::parse($str)->toDateString();
            } catch (\Throwable) {
                return null;
            }
        }

        if ($date === null) {
            return null;
        }

        // Sanity check: tahun harus 1900-2100
        $year = (int) substr($date, 0, 4);

        return ($year >= 1900 && $year <= 2100) ? $date : null;
    }

    /**
     * Parse nilai menjadi decimal string (untuk kolom uang/rate).
     *
     * @param  mixed  $value  Nilai sel mentah
     * @return string|null Nilai decimal atau null
     */
    protected function parseDecimal(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        $cleaned = str_replace([',', ' '], ['', ''], (string) $value);

        return is_numeric($cleaned) ? $cleaned : null;
    }

    /**
     * Parse nilai menjadi integer dengan batasan range.
     *
     * @param  mixed  $value  Nilai sel mentah
     * @param  int|null  $min  Nilai minimum (null = tidak dibatasi)
     * @param  int|null  $max  Nilai maksimum (null = tidak dibatasi)
     * @return int|null Nilai integer atau null jika di luar range
     */
    protected function parseInt(mixed $value, ?int $min = null, ?int $max = null): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (! is_numeric($value)) {
            return null;
        }

        $int = (int) $value;

        if ($min !== null && $int < $min) {
            return null;
        }

        if ($max !== null && $int > $max) {
            return null;
        }

        return $int;
    }

    /**
     * Parse nilai menjadi string trimed, atau default jika kosong.
     *
     * @param  mixed  $value  Nilai sel mentah
     * @param  string  $default  Nilai default
     */
    protected function parseString(mixed $value, string $default = ''): string
    {
        if ($value === null || $value === '') {
            return $default;
        }

        return trim((string) $value);
    }

    /**
     * Parse nilai menjadi boolean.
     *
     * @param  mixed  $value  Nilai sel mentah
     * @param  bool  $default  Nilai default jika kosong
     */
    protected function parseBoolean(mixed $value, bool $default = true): bool
    {
        if ($value === null || $value === '') {
            return $default;
        }

        if (is_bool($value)) {
            return $value;
        }

        return in_array(strtolower((string) $value), ['1', 'true', 'yes', 'aktif', 'active', 'ya'], true);
    }

    /**
     * Hitung total baris data (excluding header) untuk progress tracking.
     *
     * @param  ReaderInterface  $reader  Reader OpenSpout
     * @return int Total baris data
     */
    protected function countTotalRows(ReaderInterface $reader): int
    {
        $totalRows = 0;
        $isFirstRow = true;

        foreach ($reader->getSheetIterator() as $sheet) {
            foreach ($sheet->getRowIterator() as $row) {
                if ($isFirstRow) {
                    $isFirstRow = false;

                    continue; // Skip header
                }
                $totalRows++;
            }
            break; // Hanya sheet pertama
        }

        return $totalRows;
    }
}
