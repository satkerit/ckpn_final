<?php

declare(strict_types=1);

namespace App\Domain\Ckpn\Preview;

use App\Domain\Ckpn\Services\AkadEligibilityService;
use App\Enums\UsageType;
use App\Models\CalculationGeneralSetting;
use App\Models\LgdCollateralShortfallResult;
use App\Models\LgdExpectedRecoveriesResult;
use App\Models\PdMigrationResult;
use App\Models\PdNetflowResult;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Preview read-only CKPN = PD x LGD x EAD per akun untuk satu periode,
 * dipilah menjadi penelaahan Individual (Top-N outstanding terbesar) vs Kolektif.
 * Tidak menulis ke tabel snapshot hasil — hanya membaca staging + snapshot engine.
 *
 * Baseline EAD:
 *   - akad 06 / 13 / 10 → outstanding_balance periode berjalan
 *   - akad 03           → kolom tunggakan_pokok
 *
 * Rate PD/LGD diambil dari snapshot engine terbaru per segmen+periode,
 * konsisten dengan CkpnCollectiveCalculator (Ref: PRD Bab 11).
 */
final class CkpnPreviewService
{
    /** Cache rate dalam satu pemanggilan build() agar tidak query berulang per baris. */
    private array $pdRateCache = [];

    private array $lgdErCache = [];

    /**
     * Bangun hasil preview CKPN untuk satu periode — titik masuk utama service ini.
     * Ref: PRD Bab 6.1 & Bab 11
     *
     * Alur kerja:
     *   1. Ambil daftar kode akad eligible CKPN via AkadEligibilityService.
     *   2. Tentukan cutoff tanggal penilaian agunan dari calculation_parameters.
     *   3. Jalankan buildRows() — query + hitung CKPN per akun, diurutkan EAD descending.
     *   4. Ambil $topN baris pertama sebagai "Individual" (outstanding terbesar).
     *   5. Sisa baris menjadi "Collective".
     *   6. Kembalikan CkpnPreviewResult dengan dua collection + ringkasan total.
     *
     * Catatan penting:
     *   - Preview TIDAK menulis ke tabel snapshot apapun (read-only).
     *   - EAD akad 03 SEMENTARA memakai outstanding_balance karena kolom tunggakan_pokok
     *     belum tersedia (lihat TODO di dalam method ini).
     *   - Rate PD/LGD diambil dari snapshot engine terbaru untuk periode yang diminta —
     *     jika snapshot engine belum ada, rate akan 0.0 (CKPN = 0).
     *
     * @param  string  $period  Format yyyymm
     * @param  int  $topN  Jumlah akun terbesar yang diklasifikasikan sebagai Individual (min 1)
     */
    public function build(string $period, int $topN): CkpnPreviewResult
    {
        $topN = max(1, $topN);

        // TODO(PRD Bab 12): kolom tunggakan_pokok belum tersedia di financing_account_periods.
        // Sesuai keputusan user (sesi saat ini), EAD akad 03 SEMENTARA memakai outstanding_balance.
        // Saat kolom principal_arrears ditambahkan, ganti resolusi EAD akad 03 di buildRows().
        $akadCodes = AkadEligibilityService::eligibleCodes(AkadEligibilityService::KEY_CKPN, null);
        $appraisalCutoff = $this->appraisalCutoffDate();

        $rows = $this->buildRows($period, $akadCodes, $topN, $appraisalCutoff);

        $individual = $rows->take($topN)->values();
        $collective = $rows->slice($topN)->values();

        return new CkpnPreviewResult(
            period: $period,
            topN: $topN,
            individual: $individual,
            collective: $collective,
            totalEad: (float) $rows->sum(fn (CkpnPreviewRow $r) => $r->ead),
            totalCkpnIndividual: (float) $individual->sum(fn (CkpnPreviewRow $r) => $r->ckpnAmount),
            totalCkpnCollective: (float) $collective->sum(fn (CkpnPreviewRow $r) => $r->ckpnAmount),
        );
    }

    /**
     * Muat dan hitung seluruh baris CKPN untuk satu periode, kembalikan terurut EAD descending.
     * Ref: PRD Bab 11
     *
     * Menggunakan DB query builder (bukan Eloquent) demi performa — data periode bisa ±70rb baris.
     * Dua query dijalankan: satu untuk mitigasi agunan (pre-aggregated, di-keyBy account_id),
     * satu untuk baris akun + periode. Tidak ada N+1.
     *
     * Filter akun yang diterapkan:
     *   - Hanya akad dalam daftar eligible CKPN (AkadEligibilityService::KEY_CKPN)
     *   - Akad 03 dikecualikan jika belum jatuh tempo pada periode (maturity_date > akhir bulan periode)
     *
     * Per baris, dihitung:
     *   - EAD         = outstanding_balance (akad 03: SEMENTARA sama, lihat TODO)
     *   - PD rate     = dari snapshot netflow atau migration per segmen+periode (resolvePdRate)
     *   - LGD rate    = dari snapshot CS atau ER per segmen+periode (resolveLgdRate)
     *   - CKPN        = PD × LGD × EAD
     *   - Mitigasi    = SUM(estimated_sale_value) agunan aktif per akun
     *   - appraisalValid = true jika tanggal penilaian terakhir ≥ cutoff parameter
     *
     * Hasil diurutkan EAD descending sehingga take($topN) langsung menghasilkan Individual.
     *
     * @return Collection<int, CkpnPreviewRow> terurut EAD desc
     */
    private function buildRows(string $period, ?array $akadCodes, int $topN, string $appraisalCutoff): Collection
    {
        // Agregasi mitigasi agunan sekali jalan (hindari N+1):
        // nilai_diperhitungkan = SUM(estimated_sale_value), golongan_penjamin = nama tipe jaminan,
        // tanggal_penilaian_terakhir = MAX(appraised_at).
        $mitigation = DB::table('collaterals as c')
            ->leftJoin('collateral_types as ct', 'ct.id', '=', 'c.collateral_type_id')
            ->where('c.is_active', true)
            ->groupBy('c.financing_account_id')
            ->selectRaw("c.financing_account_id,
                COALESCE(SUM(c.estimated_sale_value), 0) AS mitigation_value,
                COALESCE(GROUP_CONCAT(DISTINCT ct.name SEPARATOR ', '), '') AS penjamin_group,
                MAX(c.appraised_at) AS last_appraisal_date")
            ->get()
            ->keyBy('financing_account_id');

        return DB::table('financing_account_periods as fap')
            ->join('financing_accounts as fa', 'fa.id', '=', 'fap.financing_account_id')
            ->where('fap.period', $period)
            ->where('fap.financing_status', 'A')
            ->where(function ($q): void {
                $q->whereNull('fap.writeoff_status')
                    ->orWhere('fap.writeoff_status', '!=', 'W');
            })
            ->where(function ($q): void {
                $q->whereNull('fa.product_code')
                    ->orWhere('fa.product_code', '!=', '72');
            })
            ->when($akadCodes !== null, fn ($w) => $w->whereIn('fa.akad_code', $akadCodes))
            // Akad 03 hanya jika sudah jatuh tempo pada periode — konsisten dengan engine
            ->whereNot(function ($q): void {
                $q->where('fa.akad_code', '03')->where(function ($m): void {
                    $m->whereNull('fap.maturity_date')
                        ->orWhereRaw("fap.maturity_date > LAST_DAY(STR_TO_DATE(CONCAT(fap.period, '01'), '%Y%m%d'))");
                });
            })
            ->select(
                'fap.financing_account_id',
                'fa.account_number',
                'fa.customer_name',
                'fa.akad_code',
                'fap.maturity_date',
                'fa.usage_type',
                'fap.collectibility',
                'fap.outstanding_balance',
                'fap.writeoff_status',
            )
            ->get()
            ->map(function ($row) use ($mitigation, $appraisalCutoff, $period): CkpnPreviewRow {
                $usageType = UsageType::from((int) $row->usage_type);
                $outstanding = (float) $row->outstanding_balance;

                // Baseline EAD per akad (lihat catatan TODO tunggakan_pokok di build()).
                // TODO(PRD Bab 12): akad 03 sementara = outstanding_balance sampai kolom tersedia.
                $ead = $outstanding;

                [$pdRate, $pdMethod] = $this->resolvePdRate($usageType, $period, (int) $row->collectibility);

                $isWriteoff = $row->writeoff_status === 'W';
                [$lgdRate, $lgdMethod] = $this->resolveLgdRate(
                    $usageType,
                    $period,
                    (int) $row->financing_account_id,
                    (int) $row->collectibility,
                    $isWriteoff,
                );

                $mit = $mitigation->get($row->financing_account_id);
                $lastAppraisal = $mit->last_appraisal_date ?? null;

                return new CkpnPreviewRow(
                    financingAccountId: (int) $row->financing_account_id,
                    accountNumber: (string) $row->account_number,
                    customerName: (string) ($row->customer_name ?? '-'),
                    akadCode: $row->akad_code !== null ? (string) $row->akad_code : null,
                    maturityDate: $row->maturity_date !== null ? substr((string) $row->maturity_date, 0, 10) : null,
                    usageType: $usageType,
                    collectibility: (int) $row->collectibility,
                    isWriteoff: $isWriteoff,
                    ead: $ead,
                    pdRate: $pdRate,
                    pdMethodUsed: $pdMethod,
                    lgdRate: $lgdRate,
                    lgdMethodUsed: $lgdMethod,
                    ckpnAmount: $pdRate * $lgdRate * $ead,
                    mitigationValue: (float) ($mit->mitigation_value ?? 0.0),
                    penjaminGroup: (string) ($mit->penjamin_group ?? ''),
                    lastAppraisalDate: $lastAppraisal !== null ? substr((string) $lastAppraisal, 0, 10) : null,
                    appraisalValid: $lastAppraisal !== null && substr((string) $lastAppraisal, 0, 10) >= $appraisalCutoff,
                );
            })
            ->sortByDesc(fn (CkpnPreviewRow $r) => $r->ead)
            ->values();
    }

    /**
     * Tentukan tanggal cutoff validitas penilaian agunan berdasarkan parameter sistem.
     * Ref: PRD Bab 12 (open item — nilai default 24 bulan)
     *
     * Membaca parameter 'collateral_appraisal_validity_months' dari calculation_general_settings.
     * Jika parameter tidak ada, default 24 bulan.
     *
     * Penilaian agunan dianggap valid hanya jika tanggal penilaian terakhir (appraised_at)
     * >= tanggal cutoff yang dikembalikan method ini.
     *
     * TODO(PRD Bab 12): usia maksimal penilaian agunan belum dikonfirmasi user — nilai 24 bulan SEMENTARA.
     */
    private function appraisalCutoffDate(): string
    {
        // TODO(PRD Bab 12): usia maksimal penilaian agunan belum dikonfirmasi, default 24 bulan.
        $months = max(0, CalculationGeneralSetting::intValue('collateral_appraisal_validity_months', 24));

        return now()->subMonths($months)->toDateString();
    }

    /**
     * Ambil PD rate untuk satu akun berdasarkan segmen, periode, dan kolektibilitas.
     * Ref: PRD Bab 7 & 8
     *
     * Metode PD yang dipakai (netflow/migration) dibaca dari calculation_general_settings
     * dengan key 'ckpn_collective_pd_method'.
     *
     * Logika pemilihan rate:
     *   - 'netflow'   → PdNetflowResult, key = from_bucket_id
     *                   cocokkan langsung dengan $collectibility
     *   - 'migration' → PdMigrationResult, key = from_quality_grade_id
     *                   cocokkan langsung dengan $collectibility
     *   - Jika $collectibility tidak ada dalam $rates → rata-rata semua bucket/grade
     *   - Jika $rates kosong → 0.0 (snapshot engine belum ada)
     *
     * Rate di-cache per segmen+periode agar tidak query ulang untuk setiap akun.
     *
     * @return array{float, string} [pd_rate, method_used]
     */
    private function resolvePdRate(UsageType $usageType, string $period, int $collectibility): array
    {
        $cacheKey = $usageType->value.'|'.$period;
        if (! isset($this->pdRateCache[$cacheKey])) {
            // Sumber metode PD konsisten dengan CkpnCollectiveCalculationJob
            $method = CalculationGeneralSetting::value('ckpn_collective_pd_method', 'netflow');

            $rates = $method === 'migration'
                ? PdMigrationResult::where('usage_type', $usageType->value)
                    ->where('calculation_period', $period)
                    ->pluck('pd_rate', 'from_quality_grade_id')->map(fn ($v) => (float) $v)->all()
                : PdNetflowResult::where('usage_type', $usageType->value)
                    ->where('calculation_period', $period)
                    ->pluck('pd_rate', 'from_bucket_id')->map(fn ($v) => (float) $v)->all();

            $this->pdRateCache[$cacheKey] = ['method' => $method, 'rates' => $rates];
        }

        $entry = $this->pdRateCache[$cacheKey];
        $rates = $entry['rates'];

        if ($rates === []) {
            return [0.0, $entry['method']];
        }

        if (isset($rates[$collectibility])) {
            return [(float) $rates[$collectibility], $entry['method']];
        }

        return [array_sum($rates) / count($rates), $entry['method']];
    }

    /**
     * Tentukan LGD rate per akun berdasarkan kolektibilitas dan ketersediaan snapshot CS.
     * Ref: PRD Bab 9 & 10
     *
     * Logika pemilihan:
     *   - Akun kol.5 atau write-off: coba ambil lgd_rate dari snapshot LGD Collateral Shortfall
     *     (lgd_collateral_shortfall_results) spesifik untuk akun + periode ini.
     *   - Jika snapshot CS tersedia → return [cs_rate, 'collateral_shortfall']
     *   - Jika tidak ada snapshot CS (akun tidak masuk kriteria CS) → fallback ke ER rate
     *   - Akun kol.1–4 (bukan WO): langsung pakai ER rate
     *
     * Fallback ke ER rate dilakukan via resolveLgdErRate() yang sudah di-cache per segmen.
     *
     * @return array{float, string} [lgd_rate, method_used]
     */
    private function resolveLgdRate(
        UsageType $usageType,
        string $period,
        int $financingAccountId,
        int $collectibility,
        bool $isWriteoff,
    ): array {
        $erRate = $this->resolveLgdErRate($usageType, $period);

        if ($collectibility === 5 || $isWriteoff) {
            $csRate = LgdCollateralShortfallResult::where('financing_account_id', $financingAccountId)
                ->where('calculation_period', $period)
                ->value('lgd_rate');

            if ($csRate !== null) {
                return [(float) $csRate, 'collateral_shortfall'];
            }
        }

        return [$erRate, 'expected_recoveries'];
    }

    /**
     * Ambil LGD Expected Recoveries rate untuk segmen + periode, dengan fallback all-account.
     * Ref: PRD Bab 9
     *
     * Urutan pencarian snapshot:
     *   1. Cari snapshot ER spesifik segmen (usage_type = $usageType->value)
     *   2. Jika tidak ada → fallback ke snapshot all-account (usage_type IS NULL)
     *   3. Jika keduanya tidak ada → return 0.0
     *
     * Rate di-cache per segmen+periode dalam $lgdErCache (satu key per kombinasi)
     * agar tidak query ulang untuk setiap akun dalam satu pemanggilan build().
     *
     * Konsisten dengan cara LgdFinalCalculator membaca snapshot ER sebagai input.
     */
    private function resolveLgdErRate(UsageType $usageType, string $period): float
    {
        $cacheKey = $usageType->value.'|'.$period;
        if (! isset($this->lgdErCache[$cacheKey])) {
            $rate = LgdExpectedRecoveriesResult::where('usage_type', $usageType->value)
                ->where('calculation_period', $period)
                ->value('lgd_rate');
            $scope = 'segment';

            if ($rate === null) {
                $rate = LgdExpectedRecoveriesResult::whereNull('usage_type')
                    ->where('calculation_period', $period)
                    ->value('lgd_rate');
                $scope = 'all-account';
            }

            $this->lgdErCache[$cacheKey] = [(float) ($rate ?? 0.0)];
        }

        return $this->lgdErCache[$cacheKey][0];
    }
}
