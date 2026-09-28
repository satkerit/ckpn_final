<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Pindahkan origination_date & maturity_date dari financing_accounts ke financing_account_periods.
 *
 * Alasan (Ref: PRD Bab 15): tanggal akad (tgleff) & jatuh tempo (tglexp) dapat berubah
 * karena restrukturisasi, sehingga nilai per-periode (historis) lebih relevan daripada
 * nilai tunggal di master akun. Sumber nilai baru: kolom tgleff/tglexp dari upload periode.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('financing_account_periods', function (Blueprint $table): void {
            $table->date('origination_date')->nullable()->after('period');
            $table->date('maturity_date')->nullable()->after('origination_date');
            // Index untuk filter akad 03 JTP per periode (PdNetflowBaseline, LGD-ER)
            $table->index(['period', 'maturity_date'], 'fap_period_maturity_idx');
        });

        // Copy nilai lama dari master ke semua baris periode akun tsb (best-effort)
        DB::statement(
            'UPDATE financing_account_periods fap
             JOIN financing_accounts fa ON fa.id = fap.financing_account_id
             SET fap.origination_date = fa.origination_date,
                 fap.maturity_date    = fa.maturity_date
             WHERE fa.origination_date IS NOT NULL OR fa.maturity_date IS NOT NULL'
        );

        Schema::table('financing_accounts', function (Blueprint $table): void {
            $table->dropIndex('fa_akad_maturity_idx');
            $table->dropColumn(['origination_date', 'maturity_date']);
        });
    }
};
