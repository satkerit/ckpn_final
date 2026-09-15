<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tambah composite index untuk mempercepat query export daftar debitur.
 * Query utama: WHERE fap.period IN (...) + JOIN fa ON fa.id = fap.financing_account_id
 *              + WHERE (fap.financing_status='A' OR (fap.writeoff_status='W' AND ...))
 *              + WHERE fa.usage_type = ?
 * Ref: PRD Bab 7 — PdNetflowBaseline filter
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('financing_account_periods', function (Blueprint $table): void {
            // Composite index untuk filter baseline: period + financing_status + writeoff_status
            // Menggantikan dua single index yang kurang efektif untuk query multi-kolom
            $table->index(
                ['period', 'financing_status', 'writeoff_status'],
                'fap_period_status_wo_idx'
            );

            // Index writeoff_date untuk filter LGD-ER (writeoff_date BETWEEN ...)
            $table->index('writeoff_date', 'fap_writeoff_date_idx');
        });

        Schema::table('financing_accounts', function (Blueprint $table): void {
            // Index usage_type untuk filter segmen di query pivot & export
            $table->index('usage_type', 'fa_usage_type_idx');

            // Index akad_code untuk filter akad 03 JTP
            $table->index(['akad_code', 'maturity_date'], 'fa_akad_maturity_idx');
        });
    }

    public function down(): void
    {
        Schema::table('financing_account_periods', function (Blueprint $table): void {
            $table->dropIndex('fap_period_status_wo_idx');
            $table->dropIndex('fap_writeoff_date_idx');
        });

        Schema::table('financing_accounts', function (Blueprint $table): void {
            $table->dropIndex('fa_usage_type_idx');
            $table->dropIndex('fa_akad_maturity_idx');
        });
    }
};
