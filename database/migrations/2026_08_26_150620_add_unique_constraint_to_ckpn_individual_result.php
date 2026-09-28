<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tambah unique constraint (financing_account_id, calculation_period)
 * untuk mencegah duplikat debitur pada periode yang sama.
 * Ref: PRD Bab 6.1
 */
return new class extends Migration
{
    public function up(): void
    {
        // Hapus duplikat yang sudah ada sebelum menambah constraint
        // Pertahankan baris dengan id terbesar per pasangan (financing_account_id, calculation_period)
        DB::statement('
            DELETE r1 FROM ckpn_individual_result r1
            INNER JOIN ckpn_individual_result r2
            WHERE r1.financing_account_id = r2.financing_account_id
              AND r1.calculation_period = r2.calculation_period
              AND r1.id < r2.id
        ');

        Schema::table('ckpn_individual_result', function (Blueprint $table): void {
            $table->unique(['financing_account_id', 'calculation_period'], 'uq_individual_result_account_period');
        });
    }
};
