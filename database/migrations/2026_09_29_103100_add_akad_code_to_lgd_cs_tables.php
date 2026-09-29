<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Menambahkan kolom akad_code (kode akad, level 2 segmentasi bertingkat) ke
 * tabel snapshot lgd_collateral_shortfall_result dan lgd_collateral_shortfall_by_segment_result
 * untuk mendukung perhitungan per akad.
 *
 * Ref: PRD Bab 5 (segmentasi bertingkat: kantor → jenis penggunaan → akad), Bab 10.
 */
return new class extends Migration
{
    public function up(): void
    {
        // lgd_collateral_shortfall_result (per akun)
        if (! Schema::hasColumn('lgd_collateral_shortfall_result', 'akad_code')) {
            Schema::table('lgd_collateral_shortfall_result', function (Blueprint $table): void {
                $table->string('akad_code', 10)->nullable()->after('office_code');
            });
        }

        $csResultIndexName = 'lgd_collateral_shortfall_result_akad_code_index';
        if (! Schema::hasIndex('lgd_collateral_shortfall_result', $csResultIndexName)) {
            Schema::table('lgd_collateral_shortfall_result', function (Blueprint $table) use ($csResultIndexName): void {
                $table->index('akad_code', $csResultIndexName);
            });
        }

        // lgd_collateral_shortfall_by_segment_result (per segmen)
        if (! Schema::hasColumn('lgd_collateral_shortfall_by_segment_result', 'akad_code')) {
            Schema::table('lgd_collateral_shortfall_by_segment_result', function (Blueprint $table): void {
                $table->string('akad_code', 10)->nullable()->after('office_code');
            });
        }

        $csSegmentIndexName = 'lgd_collateral_shortfall_by_segment_result_akad_code_index';
        if (! Schema::hasIndex('lgd_collateral_shortfall_by_segment_result', $csSegmentIndexName)) {
            Schema::table('lgd_collateral_shortfall_by_segment_result', function (Blueprint $table) use ($csSegmentIndexName): void {
                $table->index('akad_code', $csSegmentIndexName);
            });
        }
    }

    public function down(): void
    {
        // IRREVERSIBLE — kolom segmentasi level 2 tidak boleh hilang dari snapshot
        // jika fitur ini sudah jalan di production (Ref: AGENTS.md §9).
    }
};
