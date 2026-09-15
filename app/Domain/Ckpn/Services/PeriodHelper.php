<?php

declare(strict_types=1);

namespace App\Domain\Ckpn\Services;

/**
 * Helper untuk operasi periode format yyyymm.
 * Ref: PRD Bab 7.1
 */
final class PeriodHelper
{
    /**
     * Menggeser periode (format yyyymm) ke belakang sebanyak N bulan.
     *
     * Kriteria input:
     * - $period : string 6 digit format yyyymm, contoh '202612'
     * - $months : jumlah bulan yang digeser (harus >= 1)
     *
     * Contoh: shiftBack('202612', 1) = '202611', shiftBack('202601', 3) = '202510'
     * Ref: PRD Bab 7.1
     */
    public static function shiftBack(string $period, int $months): string
    {
        $year = (int) substr($period, 0, 4);
        $month = (int) substr($period, 4, 2);
        // Encode as 0-based month index, shift, decode back
        $total = $year * 12 + ($month - 1) - $months;
        $y = (int) floor($total / 12);
        $m = $total - ($y * 12);  // always 0-11

        return sprintf('%04d%02d', $y, $m + 1);
    }

    /**
     * Menggeser periode (format yyyymm) ke depan sebanyak N bulan.
     *
     * Kriteria input:
     * - $period : string 6 digit format yyyymm, contoh '202601'
     * - $months : jumlah bulan yang digeser (harus >= 1)
     *
     * Contoh: shiftForward('202601', 6) = '202607', shiftForward('202610', 3) = '202701'
     * Ref: PRD Bab 7.1
     */
    public static function shiftForward(string $period, int $months): string
    {
        return self::shiftBack($period, -$months);
    }

    /**
     * Hasilkan list periode dari $start sampai $end (inklusif), urut ascending.
     *
     * Kriteria input:
     * - $start dan $end : string 6 digit format yyyymm
     * - $start harus <= $end; jika $start > $end, list yang dikembalikan kosong
     *
     * Contoh: range('202601', '202603') = ['202601', '202602', '202603']
     * Digunakan untuk membangun rentang periode rolling window outstanding data.
     * Ref: PRD Bab 7.1
     *
     * @return string[]
     */
    public static function range(string $start, string $end): array
    {
        $periods = [];
        $current = $start;
        while ($current <= $end) {
            $periods[] = $current;
            $current = self::shiftForward($current, 1);
        }

        return $periods;
    }

    /**
     * Menentukan Bulan Acuan (Anchor Quarter / T) dari periode target untuk PD Migration.
     *
     * PD Migration mensyaratkan kuartal yang sudah berstatus tutup buku sebagai acuan.
     * Pemetaan bulan target ke bulan acuan (Ref: pd-migration.md Bab 1):
     *   - target 01,02,03 -> T = 12 (tahun sebelumnya)
     *   - target 04,05,06 -> T = 03
     *   - target 07,08,09 -> T = 06
     *   - target 10,11,12 -> T = 09
     *
     * @return string periode yyyymm dari anchor quarter
     */
    public static function anchorQuarter(string $period): string
    {
        $year = (int) substr($period, 0, 4);
        $month = (int) substr($period, 4, 2);

        $anchorMonth = match (true) {
            $month <= 3 => 12,
            $month <= 6 => 3,
            $month <= 9 => 6,
            default => 9,
        };

        $anchorYear = $anchorMonth === 12 ? $year - 1 : $year;

        return sprintf('%04d%02d', $anchorYear, $anchorMonth);
    }

    /**
     * Menghitung selisih bulan antara dua periode (format yyyymm).
     *
     * Kriteria:
     * - Hasil positif jika $end > $start (maju)
     * - Hasil negatif jika $end < $start (mundur)
     * - Hasil 0 jika $start == $end
     *
     * Contoh: diffMonths('202601', '202607') = 6
     * Ref: PRD Bab 7.1
     */
    public static function diffMonths(string $start, string $end): int
    {
        $sy = (int) substr($start, 0, 4);
        $sm = (int) substr($start, 4, 2);
        $ey = (int) substr($end, 0, 4);
        $em = (int) substr($end, 4, 2);

        return ($ey - $sy) * 12 + ($em - $sm);
    }
}
