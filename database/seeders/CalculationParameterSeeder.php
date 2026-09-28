<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\CalculationMethodKey;
use App\Enums\ParameterMethod;
use App\Enums\SegmentType;
use App\Models\CalculationColumnConfig;
use App\Models\CalculationDataRange;
use App\Models\CalculationGeneralSetting;
use App\Models\CalculationSegmentationLevel;
use Illuminate\Database\Seeder;

/**
 * Seed parameter kalkulasi default pada skema terstruktur baru:
 * - calculation_column_configs      → penentuan kolom EAD / PD / CKPN per POKPBY (akad_code)
 * - calculation_data_ranges         → rentang data PD & LGD (dengan segmentasi bertingkat)
 * - calculation_segmentation_levels → urutan & level segmentasi bertingkat
 * - calculation_general_settings    → parameter umum berskala tunggal
 *
 * Ref: PRD Bab 15 + Permintaan user (Penentuan Kolom, Rentang Data PD/LGD, Segmentasi Bertingkat).
 * TODO(PRD Bab 12): nilai default di bawah SEMENTARA sampai dikonfirmasi user.
 */
class CalculationParameterSeeder extends Seeder
{
    public function run(): void
    {
        $this->seedColumnConfigs();
        $this->seedDataRanges();
        $this->seedSegmentationLevels();
        $this->seedGeneralSettings();
    }

    /** Penentuan kolom tabel per POKPBY untuk EAD, dasar PD, dan dasar CKPN. */
    private function seedColumnConfigs(): void
    {
        $configs = [
            // POKPBY '10' diperlakukan khusus: EAD memakai tgkmdl & wajib sudah jatuh tempo.
            ['method' => ParameterMethod::Ead, 'pokpby_code' => '10', 'pokpby_label' => 'Khusus (tgkmdl)', 'column_name' => 'tgkmdl', 'require_maturity' => true, 'notes' => 'POKPBY 10: EAD memakai tgkmdl dan wajib sudah jatuh tempo.'],
            ['method' => ParameterMethod::Ead, 'pokpby_code' => '01', 'pokpby_label' => 'Default', 'column_name' => 'outstanding_balance', 'require_maturity' => false, 'notes' => null],
            ['method' => ParameterMethod::Ead, 'pokpby_code' => '02', 'pokpby_label' => 'Default', 'column_name' => 'outstanding_balance', 'require_maturity' => false, 'notes' => null],
            ['method' => ParameterMethod::Ead, 'pokpby_code' => '03', 'pokpby_label' => 'Default', 'column_name' => 'outstanding_balance', 'require_maturity' => true, 'notes' => 'POKPBY 03 hanya dihitung jika sudah jatuh tempo.'],
            ['method' => ParameterMethod::Ead, 'pokpby_code' => '04', 'pokpby_label' => 'Default', 'column_name' => 'outstanding_balance', 'require_maturity' => false, 'notes' => null],

            ['method' => ParameterMethod::Pd, 'pokpby_code' => '01', 'pokpby_label' => 'Default', 'column_name' => 'outstanding_balance', 'require_maturity' => false, 'notes' => null],
            ['method' => ParameterMethod::Pd, 'pokpby_code' => '02', 'pokpby_label' => 'Default', 'column_name' => 'outstanding_balance', 'require_maturity' => false, 'notes' => null],
            ['method' => ParameterMethod::Pd, 'pokpby_code' => '03', 'pokpby_label' => 'Default', 'column_name' => 'outstanding_balance', 'require_maturity' => true, 'notes' => null],
            ['method' => ParameterMethod::Pd, 'pokpby_code' => '04', 'pokpby_label' => 'Default', 'column_name' => 'outstanding_balance', 'require_maturity' => false, 'notes' => null],

            ['method' => ParameterMethod::Ckpn, 'pokpby_code' => '01', 'pokpby_label' => 'Default', 'column_name' => 'outstanding_balance', 'require_maturity' => false, 'notes' => null],
            ['method' => ParameterMethod::Ckpn, 'pokpby_code' => '02', 'pokpby_label' => 'Default', 'column_name' => 'outstanding_balance', 'require_maturity' => false, 'notes' => null],
            ['method' => ParameterMethod::Ckpn, 'pokpby_code' => '03', 'pokpby_label' => 'Default', 'column_name' => 'outstanding_balance', 'require_maturity' => true, 'notes' => null],
            ['method' => ParameterMethod::Ckpn, 'pokpby_code' => '04', 'pokpby_label' => 'Default', 'column_name' => 'outstanding_balance', 'require_maturity' => false, 'notes' => null],
        ];

        foreach ($configs as $config) {
            CalculationColumnConfig::firstOrCreate(
                ['method' => $config['method']->value, 'pokpby_code' => $config['pokpby_code']],
                [
                    'pokpby_label' => $config['pokpby_label'],
                    'source_table' => 'financing_account_periods',
                    'column_name' => $config['column_name'],
                    'require_maturity' => $config['require_maturity'],
                    'is_active' => true,
                    'notes' => $config['notes'],
                ]
            );
        }
    }

    /** Rentang data PD & LGD (level global, segmentasi bertingkat dapat ditambah lewat UI). */
    private function seedDataRanges(): void
    {
        $ranges = [
            ['method' => CalculationMethodKey::PdNetflow, 'range_key' => 'pd_netflow_rolling_window_months', 'range_value' => '36', 'range_unit' => 'months'],
            ['method' => CalculationMethodKey::PdNetflow, 'range_key' => 'pd_netflow_forward_projection_months', 'range_value' => '6', 'range_unit' => 'months'],
            ['method' => CalculationMethodKey::PdNetflow, 'range_key' => 'pd_netflow_projection_lookback_months', 'range_value' => '36', 'range_unit' => 'months'],
            ['method' => CalculationMethodKey::PdNetflow, 'range_key' => 'pd_netflow_projection_method', 'range_value' => 'rolling', 'range_unit' => 'text'],
            ['method' => CalculationMethodKey::PdMigration, 'range_key' => 'pd_migration_matrix_count', 'range_value' => '4', 'range_unit' => 'count'],
            ['method' => CalculationMethodKey::LgdExpectedRecoveries, 'range_key' => 'lgd_er_rolling_window_years', 'range_value' => '5', 'range_unit' => 'years'],
            ['method' => CalculationMethodKey::LgdExpectedRecoveries, 'range_key' => 'lgd_er_use_all_account', 'range_value' => '0', 'range_unit' => 'flag'],
            ['method' => CalculationMethodKey::LgdCollateralShortfall, 'range_key' => 'lgd_cs_selling_cost_rate', 'range_value' => '0.05', 'range_unit' => 'rate'],
        ];

        foreach ($ranges as $range) {
            CalculationDataRange::firstOrCreate(
                [
                    'method' => $range['method']->value,
                    'range_key' => $range['range_key'],
                    'office_code' => null,
                    'usage_type' => null,
                    'akad_code' => null,
                ],
                [
                    'range_value' => $range['range_value'],
                    'range_unit' => $range['range_unit'],
                    'is_active' => true,
                    'notes' => 'Nilai global default.',
                ]
            );
        }
    }

    /** Level segmentasi bertingkat: kode kantor → jenis penggunaan → akad. */
    private function seedSegmentationLevels(): void
    {
        $levels = [
            ['level_order' => 1, 'segment_type' => SegmentType::OfficeCode, 'label' => 'Kode Kantor'],
            ['level_order' => 2, 'segment_type' => SegmentType::UsageType, 'label' => 'Jenis Penggunaan'],
            ['level_order' => 3, 'segment_type' => SegmentType::AkadCode, 'label' => 'Akad'],
        ];

        foreach ($levels as $level) {
            CalculationSegmentationLevel::firstOrCreate(
                ['segment_type' => $level['segment_type']->value],
                [
                    'level_order' => $level['level_order'],
                    'label' => $level['label'],
                    'is_active' => true,
                    'allow_global_fallback' => true,
                    'notes' => null,
                ]
            );
        }
    }

    /** Parameter umum berskala tunggal (Top-N, batas NPL, biaya penjualan, metode PD, dsb.). */
    private function seedGeneralSettings(): void
    {
        $settings = [
            ['setting_key' => 'ckpn_individual_top_n_outstanding', 'setting_value' => '10', 'category' => 'ckpn', 'label' => 'Top-N Outstanding CKPN Individual', 'description' => 'Jumlah N akun outstanding terbesar untuk CKPN Individual.'],
            ['setting_key' => 'npl_min_collectibility', 'setting_value' => '3', 'category' => 'ckpn', 'label' => 'Kolektibilitas Minimum NPL', 'description' => 'Kolektibilitas minimum untuk dikategorikan NPL (Individual). Default: 3.'],
            ['setting_key' => 'ckpn_individual_selling_cost_rate', 'setting_value' => '0.05', 'category' => 'ckpn', 'label' => 'Biaya Penjualan Jaminan CKPN Individual', 'description' => 'Persentase biaya penjualan jaminan CKPN Individual (default 5%).'],
            ['setting_key' => 'ckpn_collective_pd_method', 'setting_value' => 'netflow', 'category' => 'ckpn', 'label' => 'Metode PD Default CKPN Kolektif', 'description' => 'Metode PD default untuk CKPN Kolektif (netflow | migration).'],
            ['setting_key' => 'allow_dual_pd_method', 'setting_value' => '0', 'category' => 'ckpn', 'label' => 'Izinkan Dual Metode PD', 'description' => '0 = single (ikut pd_method periode), 1 = dual (pilih sebelum hitung CKPN Kolektif).'],
            ['setting_key' => 'collateral_appraisal_validity_months', 'setting_value' => '24', 'category' => 'lgd', 'label' => 'Usia Maksimal Penilaian Agunan (bulan)', 'description' => 'Usia maksimal tanggal penilaian agunan agar mitigasi LGD dianggap valid.'],
        ];

        foreach ($settings as $setting) {
            CalculationGeneralSetting::firstOrCreate(
                ['setting_key' => $setting['setting_key']],
                [
                    'setting_value' => $setting['setting_value'],
                    'category' => $setting['category'],
                    'label' => $setting['label'],
                    'description' => $setting['description'],
                ]
            );
        }
    }
}
