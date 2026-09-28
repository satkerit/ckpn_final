<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tambah kolom pd_method dan classification_status ke ckpn_periods.
 * pd_method: metode PD yang dipilih untuk CKPN Kolektif final per periode.
 * Ref: PRD Bab 8, 12a
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ckpn_periods', function (Blueprint $table) {
            // Metode PD yang dipilih untuk CKPN Kolektif final
            $table->string('pd_method', 20)
                ->nullable()
                ->after('status')
                ->comment('netflow | migration — metode PD yang dipakai untuk CKPN Kolektif final');

            // Flag apakah klasifikasi individual/kolektif sudah dijalankan
            $table->boolean('is_classified')
                ->default(false)
                ->after('pd_method')
                ->comment('true jika ClassifyPeriodDataJob sudah selesai dijalankan');
        });
    }
};
