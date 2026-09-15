<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabel snapshot hasil CKPN Individual — insert-only per periode.
 * Ref: PRD Bab 6.1
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ckpn_individual_result', function (Blueprint $table) {
            $table->id();
            $table->foreignId('calculation_run_log_id')->constrained('calculation_run_log')->cascadeOnDelete();
            $table->foreignId('financing_account_id')->constrained('financing_accounts')->cascadeOnDelete();
            $table->string('calculation_period', 6)->comment('yyyymm');

            // Input values
            $table->decimal('outstanding_balance', 20, 2)->comment('Baki debet saat perhitungan');
            $table->decimal('total_collateral_liquidation_value', 20, 2)->comment('Total nilai likuidasi jaminan');
            $table->decimal('selling_cost_rate', 8, 6)->comment('Persentase biaya penjualan');
            $table->decimal('selling_cost_amount', 20, 2)->comment('Nominal biaya penjualan = nilai_likuidasi x rate');

            // Result
            $table->decimal('ckpn_amount', 20, 2)->comment('CKPN Individual = baki_debet - total_nilai_likuidasi - biaya_penjualan');
            $table->unsignedTinyInteger('collectibility')->comment('Kolektibilitas saat ini (3/4/5)');
            $table->boolean('is_top_n_outstanding')->default(false)->comment('Apakah masuk N outstanding terbesar');

            $table->timestamp('created_at')->useCurrent();
            // Tidak ada updated_at — snapshot immutable. Ref: AGENTS.md §4
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ckpn_individual_result');
    }
};
