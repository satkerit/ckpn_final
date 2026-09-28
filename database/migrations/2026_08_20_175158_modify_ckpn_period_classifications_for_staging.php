<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Modifikasi ckpn_period_classifications untuk mendukung alur staging:
 * - classification boleh null (belum diklasifikasi saat baru diinsert dari data historis)
 * - tambah is_classified untuk menandai apakah baris sudah diklasifikasi
 * Ref: PRD Bab 6.1, 12a Step 2-3
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ckpn_period_classifications', function (Blueprint $table) {
            // Ubah classification menjadi nullable (null = belum diklasifikasi)
            $table->string('classification', 20)->nullable()->change();

            // Flag apakah baris ini sudah diklasifikasi
            $table->boolean('is_classified')
                ->default(false)
                ->after('classification')
                ->comment('false = baru diimport dari historis, true = sudah diklasifikasi');
        });
    }
};
