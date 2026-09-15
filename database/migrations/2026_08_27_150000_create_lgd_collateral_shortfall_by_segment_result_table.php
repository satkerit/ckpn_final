<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Snapshot agregat LGD Collateral Shortfall per segmen.
 * Pelengkap lgd_collateral_shortfall_result (detail per akun) — Ref: PRD Bab 10.
 * Struktur mengikuti pola agregat LGD ER (lgd_expected_recoveries_result).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lgd_collateral_shortfall_by_segment_result', function (Blueprint $table): void {
            $table->id();
            // Nama constraint eksplisit — nama otomatis melebihi batas 64 karakter identifier MySQL.
            $table->foreignId('calculation_run_log_id')
                ->constrained('calculation_run_log', indexName: 'lgd_cs_by_segment_run_log_fk');
            $table->unsignedTinyInteger('usage_type')->nullable();
            $table->string('calculation_period', 6)->index('lgd_cs_by_segment_period_index');
            $table->unsignedInteger('account_count')->default(0);
            $table->decimal('total_outstanding', 20, 2)->default(0);
            $table->decimal('total_collateral_net_value', 20, 2)->default(0);
            $table->decimal('total_shortfall', 20, 2)->default(0);
            $table->decimal('avg_lgd_rate', 8, 6)->default(0);

            $table->unique(['calculation_run_log_id', 'usage_type'], 'lgd_cs_by_segment_run_log_utype_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lgd_collateral_shortfall_by_segment_result');
    }
};
