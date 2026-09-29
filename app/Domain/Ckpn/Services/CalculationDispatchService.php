<?php

declare(strict_types=1);

namespace App\Domain\Ckpn\Services;

use App\Enums\RunStatus;
use App\Enums\RunType;
use App\Enums\UsageType;
use App\Models\CalculationRunLog;

/**
 * Service bersama untuk membuat CalculationRunLog + dispatch job perhitungan
 * per (jenis penggunaan × target kantor × target akad).
 *
 * DESAIN SEGMENTASI (Ref: PRD Bab 5):
 *   Setiap engine dihitung untuk setiap kombinasi (jenis penggunaan, target kantor, target akad),
 *   di mana target kantor = NULL (konsolidasi seluruh kantor) ATAU satu kode kantor,
 *   dan target akad = NULL (konsolidasi seluruh akad) ATAU satu kode akad.
 *   Tiap target memakai run log SENDIRI agar status, retry, dan audit trail terpisah
 *   (Ref: AGENTS.md §4 — job idempotent, snapshot insert-only per run).
 *
 * Pemakaian (contoh PD Netflow):
 *   CalculationDispatchService::dispatchPerSegment(
 *       runType: RunType::PdNetflow,
 *       akadKey: AkadEligibilityService::KEY_PD_RATE,
 *       period: $periode,
 *       userId: auth()->id(),
 *       dispatcher: fn (CalculationRunLog $runLog, UsageType $usageType, ?string $officeCode, ?string $akadCode) =>
 *           PdNetflowCalculationJob::dispatch($runLog->id, $usageType->value, $periode, $officeCode, $akadCode),
 *   );
 */
final class CalculationDispatchService
{
    /**
     * Buat run log + dispatch untuk semua segmen (jenis penggunaan) × target kantor × target akad.
     *
     * Dua mode:
     * - $forceRerun = false → mode "jalankan": target yang sudah Completed/Approved/
     *   Pending/Processing dilewati (tidak dihitung ulang).
     * - $forceRerun = true  → mode "rekalkulasi": selalu buat run log baru (riwayat lama
     *   tetap tersimpan), tanpa memblokir status lama.
     *
     * @param  callable(CalculationRunLog, UsageType, string|null, string|null): void  $dispatcher
     * @return array{dispatched: int, skipped: int}
     */
    public static function dispatchPerSegment(
        RunType $runType,
        string $akadKey,
        string $period,
        ?int $userId,
        callable $dispatcher,
        bool $forceRerun = false,
    ): array {
        $dispatched = 0;
        $skipped = 0;

        foreach (UsageType::cases() as $usageType) {
            // Gunakan runTargetsTriplet untuk loop per officeCode × akadCode — Ref: PRD Bab 5
            foreach (OfficeSegmentResolver::runTargetsTriplet($akadKey, $usageType->value, $period) as $target) {
                $officeCode = $target['office'];
                $akadCode = $target['akad'];

                if (! $forceRerun && self::isBlocked($runType, $period, $usageType, $officeCode, $akadCode)) {
                    $skipped++;

                    continue;
                }

                $runLog = CalculationRunLog::create([
                    'period' => $period,
                    'run_type' => $runType,
                    'usage_type' => $usageType,
                    'office_code' => $officeCode,
                    'akad_code' => $akadCode,
                    'status' => RunStatus::Pending,
                    'triggered_by_user_id' => $userId,
                ]);

                $dispatcher($runLog, $usageType, $officeCode, $akadCode);
                $dispatched++;
            }
        }

        return ['dispatched' => $dispatched, 'skipped' => $skipped];
    }

    /**
     * True bila target tertentu sudah pernah/sedang dijalankan sehingga tidak boleh
     * di-dispatch ulang (Completed, Approved, Pending, atau Processing).
     */
    private static function isBlocked(RunType $runType, string $period, UsageType $usageType, ?string $officeCode, ?string $akadCode = null): bool
    {
        $query = CalculationRunLog::query()
            ->where('period', $period)
            ->where('run_type', $runType->value)
            ->where('usage_type', $usageType->value);

        $query = $officeCode === null
            ? $query->whereNull('office_code')
            : $query->where('office_code', $officeCode);

        $query = $akadCode === null
            ? $query->whereNull('akad_code')
            : $query->where('akad_code', $akadCode);

        return $query->whereIn('status', [
            RunStatus::Completed->value,
            RunStatus::Approved->value,
            RunStatus::Pending->value,
            RunStatus::Processing->value,
        ])->exists();
    }
}
