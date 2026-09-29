<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Menambahkan kolom akad_code (kode akad, level 2 segmentasi bertingkat) ke
 * tabel snapshot lgd_expected_recoveries_result untuk mendukung perhitungan per akad.
 *
 * Ref: PRD Bab 5 (segmentasi bertingkat: kantor → jenis penggunaan → akad), Bab 9.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('lgd_expected_recoveries_result', 'akad_code')) {
            Schema::table('lgd_expected_recoveries_result', function (Blueprint $table): void {
                $table->string('akad_code', 10)->nullable()->after('office_code');
            });
        }

        $indexName = 'lgd_expected_recoveries_result_akad_code_index';
        if (! Schema::hasIndex('lgd_expected_recoveries_result', $indexName)) {
            Schema::table('lgd_expected_recoveries_result', function (Blueprint $table) use ($indexName): void {
                $table->index('akad_code', $indexName);
            });
        }
    }

    public function down(): void
    {
        // IRREVERSIBLE — kolom segmentasi level 2 tidak boleh hilang dari snapshot
        // jika fitur ini sudah jalan di production (Ref: AGENTS.md §9).
    }
};
