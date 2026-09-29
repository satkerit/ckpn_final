<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Tambah parameter kustom untuk kriteria daftar perhitungan CKPN dan EAD untuk PD.
     * Parameter ini menentukan:
     * 1. Daftar POKPBY (Kode Jenis Akad) yang harus diproses dengan logika khusus
     * 2. Kriteria untuk setiap POKPBY (harus sudah jatuh tempo, gunakan tgkmdl bukan osmdlc)
     * 3. Flag untuk menentukan field EAD yang digunakan (outstanding_balance atau tgkmdl)
     *
     * Ref: Permintaan customer - untuk POKPBY='10' harus sudah jatuh tempo
     *      dan yang digunakan adalah tgkmdl bukan osmdlc/outstanding pokok
     */
    public function up(): void
    {
        // Calculation parameters migrated to new schema (calculation_column_configs).
        // This data seeded by CalculationParameterSeeder instead — skip.
    }
};
