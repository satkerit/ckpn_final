<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Menyelaraskan skema pd_migration_matrix dengan engine PD Migration yang beroperasi
 * pada UsageType (jenis penggunaan), konsisten dengan pd_migration_result & job.
 *
 * Migrasi 2026_08_21_140000 mengganti risk_segment_id -> usage_type di seluruh tabel
 * hasil, namun meng-skip pd_migration_matrix secara tidak sengaja. Tabel ini juga
 * belum pernah dipopulasi (insert-only oleh job), sehingga aman diubah.
 *
 * Ref: PRD Bab 8, AGENTS.md §3 (granularitas UsageType)
 */
return new class extends Migration
{
    public function up(): void
    {
        // Hapus risk_segment_id bila masih ada (state DB bisa bervariasi antar environment)
        if (Schema::hasColumn('pd_migration_matrix', 'risk_segment_id')) {
            Schema::table('pd_migration_matrix', function (Blueprint $table): void {
                $table->dropForeign(['risk_segment_id']);
                $table->dropUnique('pd_migration_matrix_unique');
                $table->dropColumn('risk_segment_id');
            });
        }

        // Tambah usage_type bila belum ada
        if (! Schema::hasColumn('pd_migration_matrix', 'usage_type')) {
            Schema::table('pd_migration_matrix', function (Blueprint $table): void {
                $table->unsignedTinyInteger('usage_type')->nullable()->after('calculation_run_log_id');
                $table->unique(
                    ['calculation_run_log_id', 'usage_type', 'from_quality_grade_id', 'to_quality_grade_id', 'cohort_period'],
                    'pd_migration_matrix_unique'
                );
                $table->index('usage_type');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('pd_migration_matrix', 'usage_type')) {
            Schema::table('pd_migration_matrix', function (Blueprint $table): void {
                $table->dropUnique('pd_migration_matrix_unique');
                $table->dropIndex('pd_migration_matrix_usage_type_index');
                $table->dropColumn('usage_type');
            });
        }

        if (! Schema::hasColumn('pd_migration_matrix', 'risk_segment_id')) {
            Schema::table('pd_migration_matrix', function (Blueprint $table): void {
                $table->foreignId('risk_segment_id')->constrained('risk_segments')->restrictOnDelete();
                $table->unique(
                    ['calculation_run_log_id', 'risk_segment_id', 'from_quality_grade_id', 'cohort_period'],
                    'pd_migration_matrix_unique'
                );
            });
        }
    }
};
