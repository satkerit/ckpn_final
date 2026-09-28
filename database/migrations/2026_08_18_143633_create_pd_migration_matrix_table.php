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
        Schema::create('pd_migration_matrix', function (Blueprint $table) {
            $table->id();
            $table->foreignId('calculation_run_log_id')->constrained('calculation_run_log')->restrictOnDelete();
            $table->foreignId('risk_segment_id')->constrained('risk_segments')->restrictOnDelete();
            $table->foreignId('from_quality_grade_id')->constrained('quality_grades')->restrictOnDelete();
            $table->foreignId('to_quality_grade_id')->nullable()->constrained('quality_grades')->restrictOnDelete();
            $table->char('cohort_period', 6)->notNull();
            $table->decimal('migration_rate', 10, 8)->notNull()->default(0);
            $table->decimal('source_outstanding', 20, 2)->nullable();
            $table->decimal('destination_outstanding', 20, 2)->nullable();
            $table->timestamp('created_at')->useCurrent();

            // Partial unique: excludes to_quality_grade_id because MySQL does not treat NULL as equal in UNIQUE
            $table->unique(
                ['calculation_run_log_id', 'risk_segment_id', 'from_quality_grade_id', 'cohort_period'],
                'pd_migration_matrix_unique'
            );
            $table->index('calculation_run_log_id');
            $table->index('cohort_period');
        });
    }
};
