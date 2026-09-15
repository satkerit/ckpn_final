<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Mengganti risk_segment_id dengan usage_type di tabel aggregate outstanding.
 * Menggunakan Schema builder agar portable di berbagai environment (prod/testing).
 * IRREVERSIBLE — down() melempar exception.
 */
return new class extends Migration
{
    public function up(): void
    {
        // ── financing_outstanding_monthly ──────────────────────────────────
        if (Schema::hasColumn('financing_outstanding_monthly', 'risk_segment_id')) {
            DB::statement('SET FOREIGN_KEY_CHECKS=0');

            // Drop FK jika ada (nama FK mungkin berbeda antar environment)
            $this->dropFkIfExists('financing_outstanding_monthly', 'financing_outstanding_monthly_risk_segment_id_foreign');
            $this->dropIndexIfExists('financing_outstanding_monthly', 'fom_seg_bucket_period_unique');

            Schema::table('financing_outstanding_monthly', function ($table) {
                $table->dropColumn('risk_segment_id');
            });

            DB::statement('SET FOREIGN_KEY_CHECKS=1');
        }

        if (! Schema::hasColumn('financing_outstanding_monthly', 'usage_type')) {
            Schema::table('financing_outstanding_monthly', function ($table) {
                $table->unsignedTinyInteger('usage_type')->nullable()->after('id');
                $table->unique(['usage_type', 'bucket_id', 'period'], 'fom_usage_bucket_period_unique');
            });
        }

        // ── financing_outstanding_quarterly ───────────────────────────────
        if (Schema::hasColumn('financing_outstanding_quarterly', 'risk_segment_id')) {
            DB::statement('SET FOREIGN_KEY_CHECKS=0');

            $this->dropFkIfExists('financing_outstanding_quarterly', 'financing_outstanding_quarterly_risk_segment_id_foreign');
            $this->dropIndexIfExists('financing_outstanding_quarterly', 'foq_seg_grade_period_unique');

            Schema::table('financing_outstanding_quarterly', function ($table) {
                $table->dropColumn('risk_segment_id');
            });

            DB::statement('SET FOREIGN_KEY_CHECKS=1');
        }

        if (! Schema::hasColumn('financing_outstanding_quarterly', 'usage_type')) {
            Schema::table('financing_outstanding_quarterly', function ($table) {
                $table->unsignedTinyInteger('usage_type')->nullable()->after('id');
                $table->unique(['usage_type', 'quality_grade_id', 'period'], 'foq_usage_grade_period_unique');
            });
        }
    }

    private function dropFkIfExists(string $table, string $fkName): void
    {
        $fks = DB::select("
            SELECT CONSTRAINT_NAME
            FROM information_schema.TABLE_CONSTRAINTS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = ?
              AND CONSTRAINT_TYPE = 'FOREIGN KEY'
              AND CONSTRAINT_NAME = ?
        ", [$table, $fkName]);

        if (! empty($fks)) {
            DB::statement("ALTER TABLE `{$table}` DROP FOREIGN KEY `{$fkName}`");
        }
    }

    private function dropIndexIfExists(string $table, string $indexName): void
    {
        $indexes = DB::select('
            SELECT INDEX_NAME
            FROM information_schema.STATISTICS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = ?
              AND INDEX_NAME = ?
            LIMIT 1
        ', [$table, $indexName]);

        if (! empty($indexes)) {
            DB::statement("ALTER TABLE `{$table}` DROP INDEX `{$indexName}`");
        }
    }

    public function down(): void
    {
        throw new RuntimeException('Irreversible migration — tidak dapat di-rollback.');
    }
};
