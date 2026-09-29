<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('pd_netflow_detail_breakdown', function (Blueprint $table) {
            $table->id();
            $table->foreignId('calculation_run_log_id')->constrained('calculation_run_log')->restrictOnDelete();
            $table->tinyInteger('usage_type')->unsigned()->notNull(); // UsageType::value
            $table->string('office_code', 10)->nullable(); // kantor level 1 (null = konsolidasi)
            $table->string('akad_code', 10)->nullable(); // kode akad (null = semua akad)
            $table->foreignId('from_bucket_id')->constrained('buckets')->restrictOnDelete();
            $table->char('period', 6)->notNull(); // periode observasi yyyymm
            $table->decimal('outstanding_balance', 16, 2)->notNull()->default(0); // SUM(outstanding) per lokasi+akad+bucket+periode
            $table->decimal('transition_rate', 10, 8)->nullable(); // rate B(N) periode P / B(N-1) periode P-1
            $table->decimal('compound_rate', 10, 8)->nullable(); // compound flow rate (PD) untuk bucket ini
            $table->timestamp('created_at')->useCurrent();

            // Index untuk query cepat per run + usage + lokasi + akad
            $table->unique(['calculation_run_log_id', 'usage_type', 'office_code', 'akad_code', 'from_bucket_id', 'period'], 'pd_nf_detail_unique');
            $table->index('calculation_run_log_id');
            $table->index('usage_type');
            $table->index('office_code');
            $table->index('akad_code');
            $table->index('period');
        });
    }
};
