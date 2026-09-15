<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabel hasil klasifikasi data pembiayaan per periode:
 * menentukan mana yang masuk CKPN Individual dan mana Kolektif.
 * Diisi oleh ClassifyPeriodDataJob — insert-only, idempotent per periode.
 * Ref: PRD Bab 6.1, 12a Step 3
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ckpn_period_classifications', function (Blueprint $table) {
            $table->id();

            $table->char('period', 6)->comment('yyyymm — periode yang diklasifikasi');

            $table->foreignId('financing_account_id')
                ->constrained('financing_accounts')
                ->onDelete('restrict');

            // 'individual' | 'collective'
            $table->string('classification', 20)
                ->comment('individual | collective');

            // Snapshot nilai penting saat klasifikasi dijalankan
            $table->decimal('outstanding_balance', 20, 2)->default(0);
            $table->unsignedTinyInteger('collectibility');
            $table->string('financing_status', 5)->nullable();
            $table->string('writeoff_status', 5)->nullable();

            // Alasan klasifikasi untuk audit trail
            $table->string('classification_reason', 100)->nullable()
                ->comment('mis: npl_aktif, top_n_outstanding, aktif_lancar, writeoff');

            $table->foreignId('ckpn_period_id')
                ->constrained('ckpn_periods')
                ->onDelete('restrict');

            $table->timestamp('created_at')->useCurrent();
            // Snapshot immutable — tidak ada updated_at

            $table->unique(['period', 'financing_account_id'], 'uq_classification_period_account');
            $table->index(['period', 'classification']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ckpn_period_classifications');
    }
};
