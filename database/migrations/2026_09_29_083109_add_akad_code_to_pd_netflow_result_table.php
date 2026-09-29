<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tambah kolom akad_code (level 2 segmentasi) ke pd_netflow_result.
 *
 * Sebelumnya: hanya punya office_code (level 1).
 * Sesudah: office_code + akad_code untuk breakdown per jenis akad per kantor.
 *
 * Perubahan unique constraint:
 * - Lama: (calculation_run_log_id, risk_segment_id, from_bucket_id)
 * - Baru: (calculation_run_log_id, office_code, akad_code, from_bucket_id)
 *
 * Ref: PRD Bab 5 (segmentasi level 2 = akad), Bab 7 (PD Netflow breakdown).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('pd_netflow_result', 'akad_code')) {
            Schema::table('pd_netflow_result', function (Blueprint $table): void {
                $table->string('akad_code', 2)->nullable()->after('office_code');
            });
        }

        // Tambah index untuk pencarian per akad
        if (! Schema::hasIndex('pd_netflow_result', 'pd_netflow_result_akad_code_index')) {
            Schema::table('pd_netflow_result', function (Blueprint $table): void {
                $table->index('akad_code');
            });
        }
    }

    public function down(): void
    {
        // IRREVERSIBLE — kolom segmentasi level 2 tidak boleh hilang dari snapshot
        // jika fitur ini sudah jalan di production.
    }
};
