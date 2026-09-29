<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tambah kolom akad_code (level 2 segmentasi) ke pd_migration_result dan pd_migration_matrix.
 *
 * Konsisten dengan PD Netflow: office_code (level 1) + akad_code (level 2) untuk breakdown per akad per kantor.
 *
 * Ref: PRD Bab 5 (segmentasi level 2 = akad), Bab 8 (PD Migration breakdown).
 */
return new class extends Migration
{
    public function up(): void
    {
        // pd_migration_result
        if (! Schema::hasColumn('pd_migration_result', 'akad_code')) {
            Schema::table('pd_migration_result', function (Blueprint $table): void {
                $table->string('akad_code', 10)->nullable()->after('office_code');
            });
        }

        if (! Schema::hasIndex('pd_migration_result', 'pd_migration_result_akad_code_index')) {
            Schema::table('pd_migration_result', function (Blueprint $table): void {
                $table->index('akad_code');
            });
        }

        // pd_migration_matrix
        if (! Schema::hasColumn('pd_migration_matrix', 'akad_code')) {
            Schema::table('pd_migration_matrix', function (Blueprint $table): void {
                $table->string('akad_code', 10)->nullable()->after('office_code');
            });
        }

        if (! Schema::hasIndex('pd_migration_matrix', 'pd_migration_matrix_akad_code_index')) {
            Schema::table('pd_migration_matrix', function (Blueprint $table): void {
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
