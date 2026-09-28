<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Tambah parameter ckpn_individual_top_n untuk kriteria CKPN Individual.
     * Ref: CKPN_AUDIT.md G-02 — jumlah akun NPF terbesar yang dihitung sebagai Individual
     */
    public function up(): void
    {
        DB::table('calculation_parameters')->insert([
            'parameter_key' => 'ckpn_individual_top_n',
            'parameter_value' => '100', // Default 100 akun, sesuaikan dengan kebutuhan
            'description' => 'Jumlah akun NPF dengan outstanding terbesar yang dikategorikan sebagai CKPN Individual (top N per segmen)',
            'usage_type' => null, // Global parameter
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
};
