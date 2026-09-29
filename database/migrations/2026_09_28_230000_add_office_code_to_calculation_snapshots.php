<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Menambahkan kolom office_code (kode kantor, level 1 segmentasi bertingkat) ke
 * seluruh tabel snapshot hasil kalkulasi dan calculation_run_log.
 *
 * Konvensi nilai:
 *   - NULL  = hasil konsolidasi lintas kantor (semua kantor digabung) — kompatibel
 *             dengan perilaku lama agar query consumer lama tetap bekerja.
 *   - '001' = hasil khusus untuk satu kode kantor (level 1 segmentasi).
 *
 * Catatan desain (penting):
 *   Setiap target perhitungan (konsolidasi ATAU satu kantor) memakai
 *   CalculationRunLog-nya SENDIRI (lihat OfficeSegmentResolver::runTargets()).
 *   Karena itu unique index lama yang berbasis calculation_run_log_id tetap valid
 *   dan TIDAK perlu diubah — sehingga migrasi ini tidak menyentuh foreign key
 *   (menghindari error MySQL 1553 "index needed in a foreign key constraint").
 *
 * Migration IDEMPOTEN (guard hasColumn/hasIndex) karena perubahan menyentuh banyak
 * tabel dan aman dijalankan ulang bila eksekusi terputus.
 *
 * Ref: PRD Bab 5 (segmentasi bertingkat: kantor → jenis penggunaan → akad), Bab 15.
 */
return new class extends Migration
{
    /** Tabel snapshot + posisi kolom setelahnya. */
    private const TABLES = [
        'calculation_run_log' => 'usage_type',
        'pd_netflow_result' => 'usage_type',
        'pd_migration_result' => 'usage_type',
        'pd_migration_matrix' => 'usage_type',
        'lgd_expected_recoveries_result' => 'usage_type',
        'lgd_collateral_shortfall_by_segment_result' => 'usage_type',
        'lgd_final_result' => 'usage_type',
        'lgd_collateral_shortfall_result' => 'usage_type',
        'ckpn_individual_result' => 'calculation_period',
        'ckpn_collective_result' => 'usage_type',
        'pd_netflow_consolidated' => 'from_bucket_id',
        'pd_netflow_modal_kerja' => 'from_bucket_id',
        'pd_netflow_investasi' => 'from_bucket_id',
        'pd_netflow_konsumsi' => 'from_bucket_id',
    ];

    public function up(): void
    {
        foreach (self::TABLES as $table => $after) {
            $this->addOfficeColumn($table, $after);
        }
    }

    public function down(): void
    {
        // IRREVERSIBLE — kolom segmentasi level 1 tidak boleh hilang dari snapshot
        // jika fitur ini sudah jalan di production (Ref: AGENTS.md §9).
    }

    /**
     * Tambah kolom office_code + index pencarian bila belum ada (idempoten).
     */
    private function addOfficeColumn(string $table, string $after): void
    {
        // Guard: if the "after" column doesn't exist, add after id instead
        if ($after && ! Schema::hasColumn($table, $after)) {
            $after = 'id';
        }

        if (! Schema::hasColumn($table, 'office_code')) {
            Schema::table($table, function (Blueprint $blueprint) use ($after): void {
                $blueprint->string('office_code', 10)->nullable()->after($after);
            });
        }

        $indexName = $table.'_office_code_index';
        if (! Schema::hasIndex($table, $indexName)) {
            Schema::table($table, function (Blueprint $blueprint) use ($indexName): void {
                $blueprint->index('office_code', $indexName);
            });
        }
    }
};
