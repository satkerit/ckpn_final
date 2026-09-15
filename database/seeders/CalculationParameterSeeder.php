<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\CalculationParameter;
use Illuminate\Database\Seeder;

/**
 * Seed parameter kalkulasi default.
 * Ref: PRD Bab 15 — tabel calculation_parameters
 * TODO(PRD Bab 12): nilai-nilai di bawah adalah default sementara, konfirmasi dengan user.
 */
class CalculationParameterSeeder extends Seeder
{
    public function run(): void
    {
        $params = [
            // PD Netflow
            [
                'parameter_key' => 'pd_netflow_rolling_window_months',
                'parameter_value' => '36',
                'description' => 'Panjang rolling window PD Netflow (bulan)',
                'usage_type' => null,
            ],
            [
                'parameter_key' => 'pd_netflow_forward_projection_months',
                'parameter_value' => '6',
                'description' => 'Jumlah bulan proyeksi forward PD Netflow',
                'usage_type' => null,
            ],
            [
                'parameter_key' => 'pd_netflow_projection_method',
                'parameter_value' => 'rolling',
                'description' => 'Metode rata-rata proyeksi transition rate: rolling (N bulan lookback) | full (seluruh window)',
                'usage_type' => null,
            ],
            [
                'parameter_key' => 'pd_netflow_projection_lookback_months',
                'parameter_value' => '36',
                'description' => 'Jumlah bulan lookback untuk proyeksi saat method=rolling (default = window months)',
                'usage_type' => null,
            ],
            // PD Migration
            [
                'parameter_key' => 'pd_migration_matrix_count',
                'parameter_value' => '4',
                'description' => 'Jumlah matriks migrasi PD Migration (default 4: M1=T s.d. M4=T-9, jarak 3 bulan). Ref: pd-migration.md Bab 2',
                'usage_type' => null,
            ],
            // LGD
            [
                'parameter_key' => 'lgd_er_rolling_window_years',
                'parameter_value' => '5',
                'description' => 'Panjang rolling window LGD Expected Recoveries (tahun)',
                'usage_type' => null,
            ],
            [
                'parameter_key' => 'lgd_er_use_all_account',
                'parameter_value' => '0',
                'description' => 'LGD ER: paksa fallback ke semua akun (1 = ya, 0 = hanya segmen usage type terkait)',
                'usage_type' => null,
            ],
            [
                'parameter_key' => 'lgd_cs_selling_cost_rate',
                'parameter_value' => '0.05',
                'description' => 'Persentase biaya penjualan jaminan LGD Collateral Shortfall (default 5%)',
                'usage_type' => null,
            ],
            // CKPN Individual
            [
                'parameter_key' => 'npl_min_collectibility',
                'parameter_value' => '3',
                'description' => 'Kolektibilitas minimum untuk dikategorikan NPL (Individual). Default: 3',
                'usage_type' => null,
            ],
            [
                'parameter_key' => 'ckpn_individual_top_n_outstanding',
                'parameter_value' => '10',
                'description' => 'Jumlah N akun outstanding terbesar untuk CKPN Individual',
                'usage_type' => null,
            ],
            [
                'parameter_key' => 'ckpn_individual_selling_cost_rate',
                'parameter_value' => '0.05',
                'description' => 'Persentase biaya penjualan jaminan CKPN Individual (default 5%)',
                'usage_type' => null,
            ],
            // CKPN Collective
            [
                'parameter_key' => 'ckpn_collective_pd_method',
                'parameter_value' => 'netflow',
                'description' => 'Metode PD default untuk CKPN Kolektif (netflow | migration)',
                'usage_type' => null,
            ],
            // Eligibilitas kode akad — kosong = semua akad. Akad 03 selalu hanya
            // digunakan jika sudah jatuh tempo (aturan tetap di level engine).
            [
                'parameter_key' => 'ckpn_eligible_akad_codes',
                'parameter_value' => '',
                'description' => 'Daftar kode akad dasar perhitungan CKPN, pisah koma (mis. 01,02,04). Kosong = semua akad',
                'usage_type' => null,
            ],
            [
                'parameter_key' => 'pd_rate_akad_codes',
                'parameter_value' => '',
                'description' => 'Daftar kode akad dasar perhitungan rate PD (Netflow & Migration), pisah koma. Kosong = semua akad',
                'usage_type' => null,
            ],
            [
                'parameter_key' => 'lgd_rate_akad_codes',
                'parameter_value' => '',
                'description' => 'Daftar kode akad dasar perhitungan rate LGD (ER & CS), pisah koma. Kosong = semua akad',
                'usage_type' => null,
            ],
            // CKPN Kolektif — mode metode PD
            [
                'parameter_key' => 'allow_dual_pd_method',
                'parameter_value' => '0',
                'description' => 'Izinkan 2 metode PD sebagai pembanding: 0 = single (ikut pd_method periode), 1 = dual (pilih sebelum hitung CKPN Kolektif)',
                'usage_type' => null,
            ],
            // Preview CKPN — mitigasi agunan
            [
                'parameter_key' => 'collateral_appraisal_validity_months',
                'parameter_value' => '24',
                'description' => 'Usia maksimal tanggal penilaian agunan (bulan) agar mitigasi LGD dianggap valid',
                'usage_type' => null,
            ],
        ];

        foreach ($params as $param) {
            CalculationParameter::firstOrCreate(
                [
                    'parameter_key' => $param['parameter_key'],
                    'usage_type' => $param['usage_type'] ?? null,
                ],
                $param
            );
        }
    }
}
