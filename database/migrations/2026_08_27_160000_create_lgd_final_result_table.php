<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Snapshot LGD final berbobot per segmen.
 * LGD = 1 - (Recover / OS)
 *   Recover = total_recovery_amount (ER) + total_collateral_net_value (CS)
 *   OS      = total_writeoff_amount (ER) + total_outstanding (CS)
 * Ref: PRD Bab 9, 10, 11
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lgd_final_result', function (Blueprint $table): void {
            $table->id();
            // Nama FK eksplisit — nama otomatis melebihi batas 64 karakter MySQL.
            $table->foreignId('calculation_run_log_id')
                ->constrained('calculation_run_log', indexName: 'lgd_final_run_log_fk');
            $table->unsignedTinyInteger('usage_type')->nullable();
            $table->string('calculation_period', 6)->index('lgd_final_period_index');

            // Komponen LGD ER
            $table->decimal('er_total_writeoff_amount', 20, 2)->default(0);
            $table->decimal('er_total_recovery_amount', 20, 2)->default(0);

            // Komponen LGD CS (dari snapshot agregat per segmen)
            $table->decimal('cs_total_outstanding', 20, 2)->default(0);
            $table->decimal('cs_total_shortfall', 20, 2)->default(0);

            // Nilai komposit gabungan
            $table->decimal('total_recover', 20, 2)->default(0);   // er_recovery + cs_net_value
            $table->decimal('total_os', 20, 2)->default(0);        // er_writeoff + cs_outstanding
            $table->decimal('lgd_final_rate', 8, 6)->default(0);   // 1 - (recover/os)

            $table->timestamp('created_at')->useCurrent();

            $table->unique(['calculation_run_log_id', 'usage_type'], 'lgd_final_run_log_utype_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lgd_final_result');
    }
};
