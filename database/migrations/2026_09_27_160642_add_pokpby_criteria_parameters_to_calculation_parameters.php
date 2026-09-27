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
        DB::table('calculation_parameters')->insert([
            // Parameter daftar POKPBY yang memerlukan kriteria khusus
            [
                'usage_type' => null,
                'parameter_key' => 'pokpby_special_criteria_list',
                'parameter_value' => '10', // Daftar dipisahkan koma: '10,11,12'
                'description' => 'Daftar kode POKPBY (Jenis Akad) yang memerlukan kriteria khusus dalam perhitungan CKPN/EAD',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            // Parameter mapping POKPBY ke kriteria jatuh tempo
            [
                'usage_type' => null,
                'parameter_key' => 'pokpby_require_maturity',
                'parameter_value' => '10=1', // Format: POKPBY=1 (harus jatuh tempo), POKPBY=0 (tidak)
                'description' => 'Mapping POKPBY ke requirement jatuh tempo: 1=harus sudah jatuh tempo, 0=tidak perlu',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            // Parameter mapping POKPBY ke field EAD yang digunakan
            [
                'usage_type' => null,
                'parameter_key' => 'pokpby_ead_field',
                'parameter_value' => '10=tgkmdl', // Format: POKPBY=field_name (tgkmdl atau outstanding_balance)
                'description' => 'Mapping POKPBY ke field yang digunakan untuk EAD: tgkmdl atau outstanding_balance',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            // Parameter default EAD field jika POKPBY tidak ada dalam mapping
            [
                'usage_type' => null,
                'parameter_key' => 'default_ead_field',
                'parameter_value' => 'outstanding_balance',
                'description' => 'Field default untuk EAD jika POKPBY tidak ada dalam mapping (tgkmdl atau outstanding_balance)',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }

    public function down(): void
    {
        DB::table('calculation_parameters')->whereIn('parameter_key', [
            'pokpby_special_criteria_list',
            'pokpby_require_maturity',
            'pokpby_ead_field',
            'default_ead_field',
        ])->delete();
    }
};
