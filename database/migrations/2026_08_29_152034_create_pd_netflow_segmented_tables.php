<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Membuat 4 tabel terpisah untuk PD Netflow per segmen:
     * 1. pd_netflow_consolidated (konsolidasi semua segmen)
     * 2. pd_netflow_modal_kerja
     * 3. pd_netflow_investasi
     * 4. pd_netflow_konsumsi
     */
    public function up(): void
    {
        // Struktur kolom yang sama untuk semua 4 tabel
        $createTable = function (string $tableName, string $uniquePrefix) {
            Schema::create($tableName, function (Blueprint $table) use ($uniquePrefix) {
                $table->id();
                $table->foreignId('calculation_run_log_id')->constrained('calculation_run_log')->restrictOnDelete();
                $table->foreignId('from_bucket_id')->constrained('buckets')->restrictOnDelete();
                $table->char('calculation_period', 6)->notNull();
                $table->decimal('pd_rate', 10, 8)->notNull()->default(0);
                $table->char('data_period_start', 6)->notNull();
                $table->char('data_period_end', 6)->notNull();
                $table->tinyInteger('window_months')->notNull();
                $table->decimal('transition_rate', 10, 8)->nullable()->comment('Transition rate dari bucket movement');
                $table->decimal('compound_rate', 10, 8)->nullable()->comment('Compound rate kumulatif');
                $table->decimal('source_outstanding', 20, 2)->nullable()->comment('Total outstanding sumber');
                $table->decimal('destination_outstanding', 20, 2)->nullable()->comment('Total outstanding destinasi');
                $table->text('notes')->nullable();
                $table->timestamp('created_at')->useCurrent();

                $table->unique(['calculation_run_log_id', 'from_bucket_id', 'calculation_period'], $uniquePrefix.'_run_bucket_period_unique');
                $table->index('calculation_period');
                $table->index('calculation_run_log_id');
            });
        };

        // 1. Tabel Konsolidasi (gabungan semua segmen)
        $createTable('pd_netflow_consolidated', 'pd_nf_cons');

        // 2. Tabel Modal Kerja (usage_type = 1)
        $createTable('pd_netflow_modal_kerja', 'pd_nf_mk');

        // 3. Tabel Investasi (usage_type = 2)
        $createTable('pd_netflow_investasi', 'pd_nf_inv');

        // 4. Tabel Konsumsi (usage_type = 3)
        $createTable('pd_netflow_konsumsi', 'pd_nf_kons');
    }
};
