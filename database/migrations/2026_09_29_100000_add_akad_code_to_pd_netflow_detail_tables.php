<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tambah kolom akad_code (level 3 segmentasi bertingkat) ke seluruh tabel
 * detail dan snapshot PD Netflow yang belum memilikinya.
 *
 * Konvensi nilai:
 *   - NULL  = hasil konsolidasi semua akad (backward compatible)
 *   - 'xx'  = hasil spesifik per kode akad (level 3 segmentasi)
 *
 * Tabel yang diupdate:
 *   - calculation_run_log (akad_code setelah office_code)
 *   - pd_netflow_bucket_movement
 *   - pd_netflow_compound_rate
 *   - pd_netflow_calculation_histories
 *   - pd_netflow_consolidated
 *   - pd_netflow_modal_kerja
 *   - pd_netflow_investasi
 *   - pd_netflow_konsumsi
 *
 * Migration IDEMPOTEN (guard hasColumn/hasIndex).
 * Ref: PRD Bab 5 (segmentasi 3 level: kantor → jenis penggunaan → akad)
 */
return new class extends Migration
{
    /** @var array<string, string> tabel => kolom setelahnya */
    private const TABLES = [
        'calculation_run_log' => 'office_code',
        'pd_netflow_bucket_movement' => 'office_code',
        'pd_netflow_compound_rate' => 'office_code',
        'pd_netflow_calculation_histories' => 'office_code',
        'pd_netflow_consolidated' => 'office_code',
        'pd_netflow_modal_kerja' => 'office_code',
        'pd_netflow_investasi' => 'office_code',
        'pd_netflow_konsumsi' => 'office_code',
    ];

    public function up(): void
    {
        foreach (self::TABLES as $table => $after) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            if (! Schema::hasColumn($table, 'akad_code')) {
                Schema::table($table, function (Blueprint $blueprint) use ($after): void {
                    $blueprint->string('akad_code', 10)->nullable()->after($after);
                });
            }

            $indexName = $table.'_akad_code_index';
            if (! Schema::hasIndex($table, $indexName)) {
                Schema::table($table, function (Blueprint $blueprint) use ($indexName): void {
                    $blueprint->index('akad_code', $indexName);
                });
            }
        }
    }

    public function down(): void
    {
        // IRREVERSIBLE — kolom segmentasi level 3 tidak boleh hilang dari snapshot (Ref: AGENTS.md §9).
    }
};
