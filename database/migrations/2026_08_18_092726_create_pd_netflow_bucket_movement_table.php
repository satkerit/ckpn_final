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
        Schema::create('pd_netflow_bucket_movement', function (Blueprint $table) {
            $table->id();
            $table->foreignId('calculation_run_log_id')->constrained('calculation_run_log')->restrictOnDelete();
            $table->foreignId('risk_segment_id')->constrained('risk_segments')->restrictOnDelete();
            $table->foreignId('from_bucket_id')->constrained('buckets')->restrictOnDelete();
            $table->char('period', 6)->notNull();
            $table->decimal('transition_rate', 10, 8)->notNull()->default(0);
            $table->boolean('is_projected')->default(false);
            $table->decimal('source_outstanding', 20, 2)->nullable();
            $table->decimal('destination_outstanding', 20, 2)->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['calculation_run_log_id', 'risk_segment_id', 'from_bucket_id', 'period'], 'pd_nfbm_run_log_seg_bucket_period_unique');
            $table->index('calculation_run_log_id');
            $table->index('period');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pd_netflow_bucket_movement');
    }
};
