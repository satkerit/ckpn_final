<?php

declare(strict_types=1);

namespace App\Domain\Ckpn\Preview;

use Illuminate\Support\Collection;

/**
 * Hasil preview CKPN satu periode: dua collection penelaahan
 * Individual (Top-N saldo signifikan) dan Kolektif + ringkasan total.
 *
 * Dibangun oleh CkpnPreviewService::build() — bersifat read-only, tidak disimpan ke snapshot.
 *
 * Konsep Individual vs Kolektif (Ref: PRD Bab 6.1 & Bab 11):
 *   - Individual : $topN akun dengan EAD terbesar — ditelaah satu per satu
 *   - Collective : sisa akun di luar Top-N — dihitung secara kolektif dengan formula PD×LGD×EAD
 *
 * Ringkasan total:
 *   - totalEad           : total EAD seluruh akun eligible (Individual + Collective)
 *   - totalCkpnIndividual: total CKPN penelaahan Individual
 *   - totalCkpnCollective: total CKPN penelaahan Collective
 *   - totalCkpn()        : gabungan keduanya = estimasi total CKPN periode
 */
final class CkpnPreviewResult
{
    /**
     * @param  string  $period  Periode kalkulasi format yyyymm
     * @param  int  $topN  Jumlah akun Top-N yang diklasifikasikan sebagai Individual
     * @param  Collection<int, CkpnPreviewRow>  $individual  Baris akun Individual (≤ $topN, terurut EAD desc)
     * @param  Collection<int, CkpnPreviewRow>  $collective  Baris akun Collective (sisa setelah Top-N)
     * @param  float  $totalEad  Total EAD seluruh akun eligible
     * @param  float  $totalCkpnIndividual  Total CKPN dari akun Individual
     * @param  float  $totalCkpnCollective  Total CKPN dari akun Collective
     */
    public function __construct(
        public readonly string $period,
        public readonly int $topN,
        public readonly Collection $individual,
        public readonly Collection $collective,
        public readonly float $totalEad,
        public readonly float $totalCkpnIndividual,
        public readonly float $totalCkpnCollective,
    ) {}

    /**
     * Total CKPN gabungan Individual + Collective untuk periode ini.
     * Merupakan estimasi kebutuhan cadangan CKPN sebelum finalisasi/approval.
     */
    public function totalCkpn(): float
    {
        return $this->totalCkpnIndividual + $this->totalCkpnCollective;
    }
}
