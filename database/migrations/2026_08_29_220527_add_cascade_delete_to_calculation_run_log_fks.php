<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Mengubah semua FK ke calculation_run_log menjadi ON DELETE CASCADE
 * agar hapus run log otomatis membersihkan data child di semua tabel hasil.
 */
return new class extends Migration
{
    /**
     * [tabel => [old_constraint, new_constraint]]
     * new_constraint dibuat pendek (<= 64 karakter, batas MySQL).
     */
    private array $foreignKeys = [
        'ckpn_collective_result' => ['ckpn_collective_result_calculation_run_log_id_foreign',          'ckpn_collective_result_run_log_fk'],
        'ckpn_individual_result' => ['ckpn_individual_result_calculation_run_log_id_foreign',          'ckpn_individual_result_run_log_fk'],
        'lgd_collateral_shortfall_by_segment_result' => [null,                                                             'lgd_cs_by_segment_run_log_cascade_fk'],
        'lgd_collateral_shortfall_result' => ['lgd_collateral_shortfall_result_calculation_run_log_id_foreign', 'lgd_cs_result_run_log_fk'],
        'lgd_expected_recoveries_result' => ['lgd_expected_recoveries_result_calculation_run_log_id_foreign',  'lgd_er_result_run_log_fk'],
        'lgd_final_result' => ['lgd_final_run_log_fk',                                           'lgd_final_result_run_log_fk'],
        'pd_migration_matrix' => ['pd_migration_matrix_calculation_run_log_id_foreign',             'pd_migration_matrix_run_log_fk'],
        'pd_migration_result' => ['pd_migration_result_calculation_run_log_id_foreign',             'pd_migration_result_run_log_fk'],
        'pd_netflow_bucket_movement' => ['pd_netflow_bucket_movement_calculation_run_log_id_foreign',      'pd_netflow_bucket_movement_run_log_fk'],
        'pd_netflow_calculation_histories' => ['pd_netflow_calculation_histories_calculation_run_log_id_foreign', 'pd_netflow_calc_histories_run_log_fk'],
        'pd_netflow_compound_rate' => ['pd_netflow_compound_rate_calculation_run_log_id_foreign',        'pd_netflow_compound_rate_run_log_fk'],
        'pd_netflow_consolidated' => ['pd_netflow_consolidated_calculation_run_log_id_foreign',         'pd_netflow_consolidated_run_log_fk'],
        'pd_netflow_investasi' => ['pd_netflow_investasi_calculation_run_log_id_foreign',            'pd_netflow_investasi_run_log_fk'],
        'pd_netflow_konsumsi' => ['pd_netflow_konsumsi_calculation_run_log_id_foreign',             'pd_netflow_konsumsi_run_log_fk'],
        'pd_netflow_modal_kerja' => ['pd_netflow_modal_kerja_calculation_run_log_id_foreign',          'pd_netflow_modal_kerja_run_log_fk'],
        'pd_netflow_result' => ['pd_netflow_result_calculation_run_log_id_foreign',               'pd_netflow_result_run_log_fk'],
    ];

    public function up(): void
    {
        foreach ($this->foreignKeys as $table => [$oldConstraint, $newConstraint]) {
            Schema::table($table, function (Blueprint $blueprint) use ($table, $oldConstraint, $newConstraint): void {
                // Hanya drop jika constraint lama masih ada di database
                if ($oldConstraint !== null && $this->constraintExists($table, $oldConstraint)) {
                    $blueprint->dropForeign($oldConstraint);
                }

                // Tambah FK baru dengan cascade hanya jika belum ada
                if (! $this->constraintExists($table, $newConstraint)) {
                    $blueprint->foreign('calculation_run_log_id', $newConstraint)
                        ->references('id')
                        ->on('calculation_run_log')
                        ->cascadeOnDelete();
                }
            });
        }
    }

    /** Cek apakah FK constraint sudah ada di database. */
    private function constraintExists(string $table, string $constraint): bool
    {
        $result = DB::select("
            SELECT COUNT(*) AS cnt
            FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = ?
              AND CONSTRAINT_NAME = ?
              AND CONSTRAINT_TYPE = 'FOREIGN KEY'
        ", [$table, $constraint]);

        return $result[0]->cnt > 0;
    }
};
