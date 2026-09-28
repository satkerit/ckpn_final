<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Mengganti kolom risk_segment_id dengan usage_type di seluruh tabel hasil kalkulasi.
 * Setiap tabel: drop semua FK yang backing-nya adalah unique index dulu,
 * lalu drop index + kolom, lalu tambah usage_type + restore FK.
 * IRREVERSIBLE — down() melempar exception.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Truncate semua tabel hasil kalkulasi
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        foreach (
            [
                'pd_netflow_result',
                'pd_migration_matrix',
                'pd_migration_result',
                'lgd_expected_recoveries_result',
                'lgd_collateral_shortfall_result',
                'ckpn_individual_result',
                'ckpn_collective_result',
            ] as $t
        ) {
            DB::statement("TRUNCATE TABLE `{$t}`");
        }
        DB::statement('SET FOREIGN_KEY_CHECKS=1');

        // ── pd_netflow_result ─────────────────────────────────────────────────
        // Sudah dimigrasi sebelumnya (usage_type sudah ada) — SKIP

        // ── pd_migration_matrix ───────────────────────────────────────────────
        // Sudah punya usage_type (migrasi sebelumnya), skip kolom — hanya drop risk_segment_id jika masih ada
        // Cek: tidak ada risk_segment_id di pd_migration_matrix — SKIP tabel ini

        // ── pd_migration_result ───────────────────────────────────────────────
        // pd_mig_run_log_seg_grade_unique: backing FK calculation_run_log_id dan from_quality_grade_id
        DB::statement('
            ALTER TABLE `pd_migration_result`
                DROP FOREIGN KEY `pd_migration_result_calculation_run_log_id_foreign`,
                DROP FOREIGN KEY `pd_migration_result_from_quality_grade_id_foreign`,
                DROP FOREIGN KEY `pd_migration_result_risk_segment_id_foreign`,
                DROP INDEX `pd_mig_run_log_seg_grade_unique`,
                DROP INDEX `pd_migration_result_risk_segment_id_foreign`,
                DROP COLUMN `risk_segment_id`
        ');
        DB::statement('
            ALTER TABLE `pd_migration_result`
                ADD COLUMN `usage_type` TINYINT UNSIGNED NULL AFTER `calculation_run_log_id`,
                ADD UNIQUE KEY `pd_mig_run_log_utype_grade_unique` (`calculation_run_log_id`, `usage_type`, `from_quality_grade_id`),
                ADD CONSTRAINT `pd_migration_result_calculation_run_log_id_foreign`
                    FOREIGN KEY (`calculation_run_log_id`) REFERENCES `calculation_run_log`(`id`),
                ADD CONSTRAINT `pd_migration_result_from_quality_grade_id_foreign`
                    FOREIGN KEY (`from_quality_grade_id`) REFERENCES `quality_grades`(`id`)
        ');

        // ── lgd_expected_recoveries_result ────────────────────────────────────
        // lgd_er_run_log_seg_period_unique: backing FK calculation_run_log_id dan risk_segment_id
        DB::statement('
            ALTER TABLE `lgd_expected_recoveries_result`
                DROP FOREIGN KEY `lgd_expected_recoveries_result_calculation_run_log_id_foreign`,
                DROP FOREIGN KEY `lgd_expected_recoveries_result_risk_segment_id_foreign`,
                DROP INDEX `lgd_er_run_log_seg_period_unique`,
                DROP INDEX `lgd_expected_recoveries_result_risk_segment_id_foreign`,
                DROP COLUMN `risk_segment_id`
        ');
        DB::statement('
            ALTER TABLE `lgd_expected_recoveries_result`
                ADD COLUMN `usage_type` TINYINT UNSIGNED NULL AFTER `calculation_run_log_id`,
                ADD UNIQUE KEY `lgd_er_run_log_utype_period_unique` (`calculation_run_log_id`, `usage_type`, `calculation_period`),
                ADD CONSTRAINT `lgd_expected_recoveries_result_calculation_run_log_id_foreign`
                    FOREIGN KEY (`calculation_run_log_id`) REFERENCES `calculation_run_log`(`id`)
        ');

        // ── lgd_collateral_shortfall_result ───────────────────────────────────
        // Hanya regular index (bukan backing unique) — drop FK lalu index biasa
        DB::statement('
            ALTER TABLE `lgd_collateral_shortfall_result`
                DROP FOREIGN KEY `lgd_collateral_shortfall_result_risk_segment_id_foreign`,
                DROP INDEX `lgd_collateral_shortfall_result_risk_segment_id_index`,
                DROP COLUMN `risk_segment_id`
        ');
        DB::statement('
            ALTER TABLE `lgd_collateral_shortfall_result`
                ADD COLUMN `usage_type` TINYINT UNSIGNED NULL AFTER `calculation_run_log_id`,
                ADD INDEX `lgd_cs_result_usage_type_index` (`usage_type`)
        ');

        // ── ckpn_collective_result ────────────────────────────────────────────
        // Hanya regular index FK
        DB::statement('
            ALTER TABLE `ckpn_collective_result`
                DROP FOREIGN KEY `ckpn_collective_result_risk_segment_id_foreign`,
                DROP INDEX `ckpn_collective_result_risk_segment_id_foreign`,
                DROP COLUMN `risk_segment_id`
        ');
        DB::statement('
            ALTER TABLE `ckpn_collective_result`
                ADD COLUMN `usage_type` TINYINT UNSIGNED NULL AFTER `calculation_run_log_id`
        ');

        // ── calculation_run_log ───────────────────────────────────────────────
        DB::statement('
            ALTER TABLE `calculation_run_log`
                DROP FOREIGN KEY `calculation_run_log_risk_segment_id_foreign`,
                DROP INDEX `calculation_run_log_risk_segment_id_foreign`,
                DROP COLUMN `risk_segment_id`
        ');
        DB::statement('
            ALTER TABLE `calculation_run_log`
                ADD COLUMN `usage_type` TINYINT UNSIGNED NULL AFTER `run_type`
        ');

        // ── data_quality_anomalies ────────────────────────────────────────────
        DB::statement('
            ALTER TABLE `data_quality_anomalies`
                DROP FOREIGN KEY `data_quality_anomalies_risk_segment_id_foreign`,
                DROP INDEX `data_quality_anomalies_risk_segment_id_foreign`,
                DROP COLUMN `risk_segment_id`
        ');
        DB::statement('
            ALTER TABLE `data_quality_anomalies`
                ADD COLUMN `usage_type` TINYINT UNSIGNED NULL AFTER `period`
        ');

        // ── calculation_parameters ────────────────────────────────────────────
        // unique index juga backing FK
        DB::statement('
            ALTER TABLE `calculation_parameters`
                DROP FOREIGN KEY `calculation_parameters_risk_segment_id_foreign`,
                DROP INDEX `calculation_parameters_risk_segment_id_parameter_key_unique`,
                DROP COLUMN `risk_segment_id`
        ');
        DB::statement('
            ALTER TABLE `calculation_parameters`
                ADD COLUMN `usage_type` TINYINT UNSIGNED NULL AFTER `id`,
                ADD UNIQUE KEY `calculation_parameters_usage_type_parameter_key_unique` (`usage_type`, `parameter_key`)
        ');
    }
};
