<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Fix skema fresh-install pd_netflow_result.
 *
 * Migration 2026_08_21_140000 men-skip tabel ini karena di DB lama kolom
 * usage_type sudah dimigrasi manual sebelumnya. Di DB fresh (mis. DB testing)
 * tabel masih memakai risk_segment_id tanpa usage_type, sehingga insert/query
 * gagal. Guard hasColumn membuat migration ini no-op di DB yang sudah benar.
 * Irreversible — down() melempar exception (mengikuti pola migration 140000).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('pd_netflow_result', 'usage_type')) {
            return;
        }

        // pd_nf_run_log_seg_bucket_unique: backing FK calculation_run_log_id
        // pd_netflow_result_risk_segment_id_index: backing FK risk_segment_id
        DB::statement('
            ALTER TABLE `pd_netflow_result`
                DROP FOREIGN KEY `pd_netflow_result_calculation_run_log_id_foreign`,
                DROP FOREIGN KEY `pd_netflow_result_risk_segment_id_foreign`,
                DROP INDEX `pd_nf_run_log_seg_bucket_unique`,
                DROP INDEX `pd_netflow_result_risk_segment_id_index`,
                DROP COLUMN `risk_segment_id`
        ');
        DB::statement('
            ALTER TABLE `pd_netflow_result`
                ADD COLUMN `usage_type` TINYINT UNSIGNED NULL AFTER `calculation_run_log_id`,
                ADD UNIQUE KEY `pd_nf_run_log_utype_bucket_unique` (`calculation_run_log_id`, `usage_type`, `from_bucket_id`),
                ADD CONSTRAINT `pd_netflow_result_calculation_run_log_id_foreign`
                    FOREIGN KEY (`calculation_run_log_id`) REFERENCES `calculation_run_log`(`id`)
        ');
    }

    public function down(): void
    {
        throw new RuntimeException('Irreversible migration — tidak dapat di-rollback.');
    }
};
