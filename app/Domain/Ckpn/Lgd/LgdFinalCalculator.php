<?php

declare(strict_types=1);

namespace App\Domain\Ckpn\Lgd;

use App\Enums\UsageType;
use App\Models\LgdCollateralShortfallBySegmentResult;
use App\Models\LgdExpectedRecoveriesResult;
use RuntimeException;

/**
 * Menghitung LGD final per segmen dengan menggabungkan komponen LGD ER dan LGD CS.
 *
 * Formula (Ref: PRD Bab 9, 10, 11):
 *   LGD Final = 1 - ((Total Recovery + Total Shortfall) / (Total WO + Total Outstanding))
 *   jika penyebut = 0 maka LGD = 0
 */
final class LgdFinalCalculator
{
    /**
     * Hitung LGD Final untuk SEMUA segmen (UsageType) dalam satu periode.
     * Ref: PRD Bab 9, 10, 11
     *
     * Menjalankan calculateForSegment() untuk setiap segmen yang terdaftar di enum UsageType
     * dan mengumpulkan hasilnya dalam satu array.
     *
     * Prasyarat (WAJIB terpenuhi sebelum memanggil method ini):
     *   - Snapshot LGD ER (lgd_expected_recoveries_results per segmen) sudah tersimpan
     *   - Snapshot LGD CS by-segment (lgd_collateral_shortfall_by_segment_results) sudah tersimpan
     *   Jika salah satu belum ada, method ini akan melempar RuntimeException.
     *
     * Urutan eksekusi job yang benar:
     *   1. CalculateLgdExpectedRecoveries (per segmen)
     *   2. CalculateLgdCollateralShortfall (per segmen, simpan by-segment summary)
     *   3. CalculateLgdFinal (method ini) → gabungkan ER + CS → LGD Final
     *   4. CalculateCkpnCollective (baca lgd_final_results)
     *
     * @param  string  $calculationPeriod  Format yyyymm, mis. 202412
     * @return array<int, array{
     *   usage_type: UsageType,
     *   er_total_writeoff_amount: float,
     *   er_total_recovery_amount: float,
     *   cs_total_outstanding: float,
     *   cs_total_shortfall: float,
     *   total_recover: float,
     *   total_os: float,
     *   lgd_final_rate: float,
     * }>
     *
     * @throws RuntimeException jika salah satu komponen belum tersedia untuk segmen manapun.
     */
    public function calculateAll(string $calculationPeriod): array
    {
        $results = [];

        foreach (UsageType::cases() as $usageType) {
            $results[] = $this->calculateForSegment($usageType, $calculationPeriod);
        }

        return $results;
    }

    /**
     * Hitung LGD Final untuk SATU segmen dalam satu periode.
     * Ref: PRD Bab 9, 10, 11
     *
     * Formula penggabungan LGD ER + LGD CS:
     *   Total WO  = er_total_writeoff_amount + cs_total_outstanding
     *   Total LGD = (er_total_writeoff_amount − er_total_recovery_amount) + cs_total_shortfall
     *   LGD Final = MAX(0, MIN(1, Total LGD / Total WO))
     *   Jika Total WO = 0 → LGD Final = 0
     *
     * Interpretasi komponen:
     *   - LGD ER (Expected Recoveries): mengukur kerugian dari portofolio yang sudah WO
     *     → er_total_writeoff_amount = total outstanding saat WO (dalam window 5 tahun)
     *     → er_total_recovery_amount = total outstanding yang masih ada di periode ini (sudah recovered)
     *   - LGD CS (Collateral Shortfall): mengukur kekurangan agunan untuk akun kualitas 5 + WO
     *     → cs_total_outstanding = total baki debet akun eligible
     *     → cs_total_shortfall  = total kekurangan agunan dari akun eligible
     *
     * Prasyarat:
     *   - Snapshot LGD ER untuk segmen ini sudah ada di lgd_expected_recoveries_results
     *   - Snapshot LGD CS by-segment untuk segmen ini sudah ada di lgd_collateral_shortfall_by_segment_results
     *
     * @param  string  $calculationPeriod  Format yyyymm, mis. 202412
     * @return array{
     *   usage_type: UsageType,
     *   er_total_writeoff_amount: float,
     *   er_total_recovery_amount: float,
     *   cs_total_outstanding: float,
     *   cs_total_shortfall: float,
     *   total_recover: float,
     *   total_os: float,
     *   lgd_final_rate: float,
     * }
     *
     * @throws RuntimeException jika snapshot ER atau CS untuk segmen ini belum ada.
     */
    public function calculateForSegment(UsageType $usageType, string $calculationPeriod): array
    {
        // Ambil snapshot LGD ER per segmen
        $er = LgdExpectedRecoveriesResult::where('usage_type', $usageType->value)
            ->where('calculation_period', $calculationPeriod)
            ->first(['total_writeoff_amount', 'total_recovery_amount']);

        if ($er === null) {
            throw new RuntimeException(
                "LGD ER belum tersedia untuk segmen {$usageType->name} periode {$calculationPeriod}. "
                    .'Jalankan perhitungan LGD Expected Recoveries terlebih dahulu.',
            );
        }

        // Ambil snapshot LGD CS by-segment (kolom: total_outstanding, total_shortfall)
        $cs = LgdCollateralShortfallBySegmentResult::where('usage_type', $usageType->value)
            ->where('calculation_period', $calculationPeriod)
            ->first(['total_outstanding', 'total_shortfall']);

        if ($cs === null) {
            throw new RuntimeException(
                "LGD CS (by segment) belum tersedia untuk segmen {$usageType->name} periode {$calculationPeriod}. "
                    .'Jalankan perhitungan LGD Collateral Shortfall terlebih dahulu.',
            );
        }

        $erWriteoff = (float) $er->total_writeoff_amount;
        $erRecovery = (float) $er->total_recovery_amount;
        $csOs = (float) $cs->total_outstanding;
        $csShortfall = (float) $cs->total_shortfall;

        // Total WO  = Total OS Writeoff (ER) + Total Outstanding (CS) — Ref: PRD Bab 11
        // Total LGD = (Total OS Writeoff - Recovery Writeoff) + Total Shortfall
        // LGD Rate  = Total LGD / Total WO
        $totalWo = $erWriteoff + $csOs;
        $totalLgd = ($erWriteoff - $erRecovery) + $csShortfall;
        $lgdFinalRate = $totalWo > 0.0 ? max(0.0, min($totalLgd / $totalWo, 1.0)) : 0.0;

        return [
            'usage_type' => $usageType,
            'er_total_writeoff_amount' => $erWriteoff,
            'er_total_recovery_amount' => $erRecovery,
            'cs_total_outstanding' => $csOs,
            'cs_total_shortfall' => $csShortfall,
            'total_lgd' => $totalLgd,
            'total_wo' => $totalWo,
            'lgd_final_rate' => $lgdFinalRate,
        ];
    }
}
