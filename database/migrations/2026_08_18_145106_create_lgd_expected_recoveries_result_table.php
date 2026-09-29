<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lgd_expected_recoveries_result', function (Blueprint $table) {
            $table->id();
            $table->foreignId('calculation_run_log_id')->constrained('calculation_run_log')->restrictOnDelete();
            $table->foreignId('risk_segment_id')->nullable()->constrained('risk_segments')->restrictOnDelete();
            $table->char('calculation_period', 6)->notNull();
            $table->char('data_period_start', 6)->notNull();
            $table->char('data_period_end', 6)->notNull();
            $table->tinyInteger('window_years')->notNull()->default(5);
            $table->decimal('total_writeoff_amount', 20, 2)->notNull()->default(0);
            $table->decimal('total_recovery_amount', 20, 2)->notNull()->default(0);
            $table->decimal('expected_recovery_rate', 10, 8)->notNull()->default(0);
            $table->decimal('lgd_rate', 10, 8)->notNull()->default(0);
            $table->boolean('is_all_account')->default(false);
            $table->string('usage_type')->nullable();
            $table->string('office_code')->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('created_at')->useCurrent();

            // risk_segment_id nullable: unique per run per segment (NULL treated as distinct per DB)
            $table->unique(['calculation_run_log_id', 'risk_segment_id', 'calculation_period'], 'lgd_er_run_log_seg_period_unique');
            $table->index('calculation_period');
        });
    }
};
