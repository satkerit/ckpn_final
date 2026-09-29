<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tambah kolom notes (dasar data) pada tabel hasil/snapshot perhitungan CKPN.
 * Ref: instruksi user — catatan dasar data perhitungan per baris hasil.
 * Satu perubahan logis: penambahan kolom catatan ke semua tabel hasil.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Notes columns now part of base migrations — skip.
    }
};
