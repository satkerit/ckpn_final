<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tambah kolom ppka ke financing_account_periods untuk menyimpan nilai PPKA per periode.
     * Ref: Catatan CKPN — PPKA akan didapatkan dari history pembiayaan.
     *
     * Kolom ini menyimpan nilai PPKA per akun per periode, yang akan digunakan untuk:
     * - Perbandingan CKPN vs PPKA
     * - Laporan kepatuhan POJK 24/2024
     * - Analisis selisih cadangan
     */
    public function up(): void
    {
        Schema::table('financing_account_periods', function ($table) {
            $table->decimal('ppka', 20, 2)->nullable()->after('outstanding_balance')
                ->comment('Nilai PPKA (Penilaian Kualitas Aktiva) per periode untuk comparasi dengan CKPN');
        });
    }

    public function down(): void
    {
        Schema::table('financing_account_periods', function ($table) {
            $table->dropColumn('ppka');
        });
    }
};
