<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tambah office_code + akad_code ke ckpn_period_classifications + composite index.
 * Diperlukan untuk segmentasi 3-level (kantor → akad → usage_type).
 * Ref: PRD Bab 5, 7, Audit Alur CKPN Gap #1
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ckpn_period_classifications', function (Blueprint $table) {
            // Cek apakah kolom sudah ada sebelum menambah
            if (! Schema::hasColumn('ckpn_period_classifications', 'office_code')) {
                $table->string('office_code', 10)->nullable()
                    ->after('usage_type')
                    ->comment('Kode kantor/cabang untuk segmentasi level 1');
            }

            if (! Schema::hasColumn('ckpn_period_classifications', 'akad_code')) {
                $table->string('akad_code', 10)->nullable()
                    ->after('office_code')
                    ->comment('Kode akad pembiayaan untuk segmentasi level 2');
            }

            $table->index(['period', 'office_code', 'akad_code', 'usage_type'], 'idx_segmentation');
        });
    }

    public function down(): void
    {
        Schema::table('ckpn_period_classifications', function (Blueprint $table) {
            $table->dropIndex('idx_segmentation');
            $table->dropColumnIfExists(['office_code', 'akad_code']);
        });
    }
};
