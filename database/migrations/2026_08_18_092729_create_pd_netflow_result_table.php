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
        Schema::create('pd_netflow_result', function (Blueprint $table) {
            $table->id();
            $table->foreignId('calculation_run_log_id')->constrained('calculation_run_log')->restrictOnDelete();
            $table->foreignId('risk_segment_id')->constrained('risk_segments')->restrictOnDelete();
            $table->foreignId('from_bucket_id')->constrained('buckets')->restrictOnDelete();
            $table->char('calculation_period', 6)->notNull();
            $table->decimal('pd_rate', 10, 8)->notNull()->default(0);
            $table->char('data_period_start', 6)->notNull();
            $table->char('data_period_end', 6)->notNull();
            $table->tinyInteger('window_months')->notNull();
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['calculation_run_log_id', 'risk_segment_id', 'from_bucket_id'], 'pd_nf_run_log_seg_bucket_unique');
            $table->index('calculation_period');
            $table->index('risk_segment_id');
        });
    }
};
