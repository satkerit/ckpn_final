<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tambah kolom notes (dasar data) pada tabel hasil/snapshot perhitungan CKPN.
 * Ref: instruksi user — catatan dasar data perhitungan per baris hasil.
 * Satu perubahan logis: penambahan kolom catatan ke semua tabel hasil.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pd_netflow_result', function (Blueprint $table) {
            $table->text('notes')->nullable()->after('window_months')
                ->comment('Dasar data: sumber tabel, filter, window, periode');
        });

        Schema::table('pd_migration_result', function (Blueprint $table) {
            $table->text('notes')->nullable()->after('data_period_end')
                ->comment('Dasar data: sumber tabel, filter, window, cohort');
        });

        Schema::table('lgd_expected_recoveries_result', function (Blueprint $table) {
            $table->text('notes')->nullable()->after('lgd_rate')
                ->comment('Dasar data: sumber, filter writeoff, window, total');
        });

        Schema::table('lgd_collateral_shortfall_result', function (Blueprint $table) {
            $table->text('notes')->nullable()->after('lgd_rate')
                ->comment('Dasar data: sumber, filter agunan, nilai jual bersih');
        });

        Schema::table('lgd_collateral_shortfall_by_segment_result', function (Blueprint $table) {
            $table->text('notes')->nullable()->after('avg_lgd_rate')
                ->comment('Dasar data agregat per segmen');
        });

        Schema::table('ckpn_individual_result', function (Blueprint $table) {
            $table->text('notes')->nullable()->after('collectibility')
                ->comment('Dasar data: sumber staging, filter, selling_cost_rate');
        });

        Schema::table('ckpn_collective_result', function (Blueprint $table) {
            $table->text('notes')->nullable()->after('ckpn_amount')
                ->comment('Dasar data: sumber, pd_method, lgd_method, filter');
        });
    }
};
