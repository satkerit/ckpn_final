<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Memperbaiki unique index pd_migration_matrix agar mencakup to_quality_grade_id.
 *
 * Satu baris (calculation_run_log_id, usage_type, from_quality_grade_id, cohort_period)
 * mewakili BANYAK transisi ke kualitas tujuan berbeda (grade 1..N + Write-Off).
 * Unique lama tanpa to_quality_grade_id menyebabkan benturan saat insert detail matriks.
 *
 * Ref: PRD Bab 8 — satu baris per (from_grade → to_grade) per cohort.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pd_migration_matrix', function (Blueprint $table): void {
            $table->dropUnique('pd_migration_matrix_unique');
            $table->unique(
                ['calculation_run_log_id', 'usage_type', 'from_quality_grade_id', 'to_quality_grade_id', 'cohort_period'],
                'pd_migration_matrix_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::table('pd_migration_matrix', function (Blueprint $table): void {
            $table->dropUnique('pd_migration_matrix_unique');
            $table->unique(
                ['calculation_run_log_id', 'usage_type', 'from_quality_grade_id', 'cohort_period'],
                'pd_migration_matrix_unique'
            );
        });
    }
};
