<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabel snapshot hasil CKPN Kolektif — insert-only per periode.
 * Ref: PRD Bab 11
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ckpn_collective_result', function (Blueprint $table) {
            $table->id();
            $table->foreignId('calculation_run_log_id')->constrained('calculation_run_log')->cascadeOnDelete();
            $table->foreignId('financing_account_id')->constrained('financing_accounts')->cascadeOnDelete();
            $table->foreignId('risk_segment_id')->constrained('risk_segments')->cascadeOnDelete();
            $table->string('calculation_period', 6)->comment('yyyymm');

            // PD
            $table->string('pd_method_used', 20)->comment('netflow | migration');
            $table->decimal('pd_rate', 8, 6)->comment('PD yang dipakai untuk akun ini');

            // LGD
            $table->string('lgd_method_used', 20)->comment('expected_recoveries | collateral_shortfall');
            $table->decimal('lgd_rate', 8, 6)->comment('LGD yang dipakai untuk akun ini');

            // EAD
            $table->decimal('ead', 20, 2)->comment('Exposure at Default = outstanding balance periode ini');

            // Result
            $table->decimal('ckpn_amount', 20, 2)->comment('CKPN Kolektif = PD x LGD x EAD');

            $table->timestamp('created_at')->useCurrent();
            // Tidak ada updated_at — snapshot immutable. Ref: AGENTS.md §4
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ckpn_collective_result');
    }
};
