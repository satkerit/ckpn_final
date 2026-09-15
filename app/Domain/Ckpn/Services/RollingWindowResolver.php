<?php

declare(strict_types=1);

namespace App\Domain\Ckpn\Services;

/**
 * Resolves rolling window boundaries for PD Netflow/Migration calculations.
 * Ref: PRD Bab 7.1 & 7.4
 *
 * Contoh (window=36, forward=6, calculationPeriod=202612):
 *   projectionStart        = 202601
 *   outstandingEnd         = 202512
 *   outstandingStart       = 202212
 *   rateStart              = 202301
 *   projectionLookbackStart= 202507
 *   compoundFlowEnd        = 202512
 */
final class RollingWindowResolver
{
    public function __construct(
        private readonly int $windowMonths,
        private readonly int $forwardProjectionMonths,
    ) {}

    /**
     * Mengembalikan jumlah bulan proyeksi ke depan yang dikonfigurasi untuk engine ini.
     *
     * Nilai ini dibaca dari parameter `pd_netflow_forward_projection_months` di tabel
     * `calculation_parameters`. Digunakan oleh PdNetflowCalculator untuk menentukan
     * panjang rantai compound flow ke depan.
     */
    public function forwardProjectionMonths(): int
    {
        return $this->forwardProjectionMonths;
    }

    /**
     * Mengembalikan periode awal proyeksi ke depan (calculationPeriod + 1 bulan).
     *
     * Ini adalah periode pertama yang BELUM memiliki data outstanding aktual —
     * nilai outstanding-nya akan diproyeksikan menggunakan rata-rata transition rate
     * dari lookback window.
     *
     * Contoh: calculationPeriod = 202512 → projectionStart = 202601
     * Ref: PRD Bab 7.4
     */
    public function projectionStartPeriod(string $calculationPeriod): string
    {
        return PeriodHelper::shiftForward($calculationPeriod, 1);
    }

    /**
     * Mengembalikan periode akhir data outstanding aktual (= calculationPeriod itu sendiri).
     *
     * Data outstanding tersedia sampai periode perhitungan; periode sesudahnya adalah proyeksi.
     * Contoh: calculationPeriod = 202512 → outstandingEnd = 202512
     * Ref: PRD Bab 7.1
     */
    public function outstandingEndPeriod(string $calculationPeriod): string
    {
        return $calculationPeriod;
    }

    /**
     * Mengembalikan periode awal data outstanding yang dibutuhkan engine.
     *
     * Rumus: outstandingEnd - windowMonths bulan ke belakang.
     * Jumlah titik data outstanding = windowMonths + 1 (inklusif kedua ujung).
     *
     * Kriteria:
     * - windowMonths dikonfigurasi via parameter `pd_netflow_window_months` (default 36).
     * - Data outstanding harus tersedia lengkap dari outstandingStart sampai outstandingEnd;
     *   jika ada bulan kosong, validator akan men-flag anomali.
     *
     * Contoh: window=36, outstandingEnd=202512 → outstandingStart = 202212 (37 titik data)
     * Ref: PRD Bab 7.1
     */
    public function outstandingStartPeriod(string $calculationPeriod): string
    {
        return PeriodHelper::shiftBack($this->outstandingEndPeriod($calculationPeriod), $this->windowMonths);
    }

    /**
     * Mengembalikan periode awal perhitungan transition rate (= outstandingStart + 1 bulan).
     *
     * Transition rate dihitung dari pergerakan outstanding antar dua periode berurutan,
     * sehingga periode pertama yang bisa dihitung rate-nya adalah bulan kedua data outstanding.
     *
     * Kriteria: rateStart = outstandingStart + 1 bulan
     * Contoh: outstandingStart = 202212 → rateStart = 202301
     * Ref: PRD Bab 7.1
     */
    public function rateStartPeriod(string $calculationPeriod): string
    {
        return PeriodHelper::shiftForward($this->outstandingStartPeriod($calculationPeriod), 1);
    }

    /**
     * Mengembalikan periode akhir untuk perhitungan transition rate (= calculationPeriod).
     *
     * Rate dihitung dari rateStart sampai periode ini menggunakan data outstanding aktual.
     * Periode proyeksi sesudahnya menggunakan rata-rata historis dari lookback window.
     * Ref: PRD Bab 7.1
     */
    public function rateEndPeriod(string $calculationPeriod): string
    {
        return $calculationPeriod;
    }

    /**
     * Mengembalikan periode awal lookback window untuk menghitung rata-rata transition rate proyeksi.
     *
     * Rata-rata transition rate pada periode proyeksi dihitung dari data historis
     * sebanyak forwardProjectionMonths bulan ke belakang dari projectionStart.
     *
     * Rumus: projectionStart - forwardProjectionMonths bulan
     * Kriteria: forwardProjectionMonths dikonfigurasi via parameter `pd_netflow_forward_projection_months`.
     *
     * Contoh: projectionStart=202601, forward=6 → projectionLookbackStart = 202507
     * Ref: PRD Bab 7.4
     */
    public function projectionLookbackStart(string $calculationPeriod): string
    {
        return PeriodHelper::shiftBack($this->projectionStartPeriod($calculationPeriod), $this->forwardProjectionMonths);
    }

    /**
     * Mengembalikan periode akhir rantai compound flow diagonal (= calculationPeriod + forwardProjectionMonths).
     *
     * Compound flow dihitung secara diagonal: dimulai dari setiap bucket pada calculationPeriod,
     * diteruskan ke depan sebanyak forwardProjectionMonths langkah menggunakan projected transition rate.
     * Periode ini adalah titik akhir rantai diagonal tersebut.
     *
     * Kriteria: compoundEnd = calculationPeriod + forwardProjectionMonths bulan
     * Contoh: calculationPeriod=202512, forward=6 → compoundEnd = 202606
     * Ref: PRD Bab 7.4
     */
    public function compoundFlowEndPeriod(string $calculationPeriod): string
    {
        return PeriodHelper::shiftForward($calculationPeriod, $this->forwardProjectionMonths);
    }
}
