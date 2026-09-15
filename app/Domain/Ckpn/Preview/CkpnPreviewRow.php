<?php

declare(strict_types=1);

namespace App\Domain\Ckpn\Preview;

use App\Enums\UsageType;

/**
 * Satu baris hasil preview CKPN per akun pembiayaan.
 * Ref: PRD Bab 6.1 / Bab 11 — CKPN = PD x LGD x EAD.
 *
 * Dibuat oleh CkpnPreviewService::buildRows() — bersifat immutable (readonly).
 *
 * Penjelasan field utama:
 *   - ead           : Exposure at Default = outstanding_balance (akad 06/13/10)
 *                     atau tunggakan_pokok (akad 03, SEMENTARA = outstanding)
 *   - pdRate        : PD rate dari snapshot netflow atau migration per segmen
 *   - pdMethodUsed  : 'netflow' atau 'migration'
 *   - lgdRate       : LGD rate — CS jika kol.5/WO dengan snapshot CS, ER jika tidak
 *   - lgdMethodUsed : 'collateral_shortfall' atau 'expected_recoveries'
 *   - ckpnAmount    : pdRate × lgdRate × ead
 *
 * Mitigasi agunan (kolom mitigationValue, penjaminGroup, lastAppraisalDate):
 *   - Hanya akun yang memiliki agunan aktif (is_active=true) yang terisi nilai mitigasi.
 *   - appraisalValid = true jika lastAppraisalDate >= cutoff parameter sistem
 *     (parameter 'collateral_appraisal_validity_months').
 */
final class CkpnPreviewRow
{
    public function __construct(
        public readonly int $financingAccountId,
        public readonly string $accountNumber,
        public readonly string $customerName,
        public readonly ?string $akadCode,
        /** Tanggal jatuh tempo akad dari periode berjalan */
        public readonly ?string $maturityDate,
        public readonly UsageType $usageType,
        public readonly int $collectibility,
        public readonly bool $isWriteoff,
        /** Baseline EAD (akad 06/13/10 = outstanding; akad 03 = tunggakan pokok) */
        public readonly float $ead,
        public readonly float $pdRate,
        public readonly string $pdMethodUsed,
        public readonly float $lgdRate,
        public readonly string $lgdMethodUsed,
        public readonly float $ckpnAmount,
        /** Mitigasi LGD: nilai agunan yang diperhitungkan (SUM estimated_sale_value) */
        public readonly float $mitigationValue,
        /** Mitigasi LGD: golongan penjamin (nama tipe jaminan) */
        public readonly string $penjaminGroup,
        /** Mitigasi LGD: tanggal penilaian terakhir agunan */
        public readonly ?string $lastAppraisalDate,
        /** True jika tanggal penilaian masih dalam batas usia parameter */
        public readonly bool $appraisalValid,
    ) {}
}
