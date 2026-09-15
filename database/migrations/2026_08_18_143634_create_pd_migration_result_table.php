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
        Schema::create('pd_migration_result', function (Blueprint $table) {
            $table->id();
            $table->foreignId('calculation_run_log_id')->constrained('calculation_run_log')->restrictOnDelete();
            $table->foreignId('risk_segment_id')->constrained('risk_segments')->restrictOnDelete();
            $table->foreignId('from_quality_grade_id')->constrained('quality_grades')->restrictOnDelete();
            $table->char('calculation_period', 6)->notNull();
            $table->decimal('pd_rate', 10, 8)->notNull()->default(0);
            $table->tinyInteger('cohort_count')->notNull()->default(0);
            $table->char('data_period_start', 6)->notNull();
            $table->char('data_period_end', 6)->notNull();
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['calculation_run_log_id', 'risk_segment_id', 'from_quality_grade_id'], 'pd_mig_run_log_seg_grade_unique');
            $table->index('calculation_period');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pd_migration_result');
    }
};
