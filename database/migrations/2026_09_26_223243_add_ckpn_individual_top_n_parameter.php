<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Tambah parameter ckpn_individual_top_n untuk kriteria CKPN Individual.
     * Ref: CKPN_AUDIT.md G-02 — jumlah akun NPF terbesar yang dihitung sebagai Individual
     */
    public function up(): void
    {
        // Calculation parameters migrated to new schema (calculation_general_settings).
        // This parameter seeded by CalculationParameterSeeder instead — skip.
    }
};
