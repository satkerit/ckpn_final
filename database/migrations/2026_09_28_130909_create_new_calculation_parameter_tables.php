<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Modul Parameter Kalkulasi baru — terstruktur per topik.
 * Ref: PRD Bab 15 + Permintaan User (Penentuan Kolom, Rentang Data PD/LGD, Segmentasi Bertingkat).
 */
return new class extends Migration
{
    public function up(): void
    {
        // 1. Penentuan Kolom Tabel per POKPBY (akad_code) untuk EAD / dasar PD / dasar CKPN
        Schema::create('calculation_column_configs', function (Blueprint $table) {
            $table->id();
            $table->string('method', 30);                   // ead | pd | ckpn
            $table->string('pokpby_code', 10);              // POKPBY / akad_code (mis. '10', '01', '03')
            $table->string('pokpby_label', 100)->nullable();
            $table->string('source_table', 60)->default('financing_account_periods');
            $table->string('column_name', 60);              // outstanding_balance | tgkmdl | ppka | collectibility
            $table->boolean('require_maturity')->default(false); // Khusus POKPBY yang harus sudah jatuh tempo
            $table->boolean('is_active')->default(true);
            $table->string('notes', 255)->nullable();
            $table->timestamps();

            $table->unique(['method', 'pokpby_code'], 'uq_column_config_method_pokpby');
            $table->index('method');
        });

        // 2. Rentang Data PD & LGD (rolling window, forward projection, lookback, matrix count)
        //    Mendukung Segmentasi Bertingkat (office_code, usage_type, akad_code) per baris parameter.
        Schema::create('calculation_data_ranges', function (Blueprint $table) {
            $table->id();
            $table->string('method', 30);                   // pd_netflow | pd_migration | lgd_expected_recoveries | lgd_collateral_shortfall
            $table->string('range_key', 60);                // rolling_window_months | forward_projection_months | projection_lookback_months | matrix_count | rolling_window_years | selling_cost_rate
            $table->string('range_value', 100);             // Nilai angka / string (mis. '36', '6', 'rolling', '0.05')
            $table->string('range_unit', 20)->default('months');
            // Segmentasi bertingkat (NULL = berlaku global / fallback)
            $table->string('office_code', 10)->nullable();
            $table->unsignedTinyInteger('usage_type')->nullable(); // 1=Modal Kerja, 2=Investasi, 3=Konsumsi
            $table->string('akad_code', 10)->nullable();
            $table->boolean('is_active')->default(true);
            $table->string('notes', 255)->nullable();
            $table->timestamps();

            $table->index(['method', 'range_key']);
            $table->index(['office_code', 'usage_type', 'akad_code'], 'idx_data_range_segment');
        });

        // 3. Konfigurasi Level Segmentasi Bertingkat (Urutan & Status Aktif)
        Schema::create('calculation_segmentation_levels', function (Blueprint $table) {
            $table->id();
            $table->unsignedTinyInteger('level_order');     // 1 = terluar (mis. Kode Kantor), 2 = (Jenis Penggunaan), 3 = (Akad)
            $table->string('segment_type', 30);             // office_code | usage_type | akad_code
            $table->string('label', 60);
            $table->boolean('is_active')->default(true);
            $table->boolean('allow_global_fallback')->default(true);
            $table->string('notes', 255)->nullable();
            $table->timestamps();

            $table->unique('level_order', 'uq_segmentation_level_order');
            $table->unique('segment_type', 'uq_segmentation_level_type');
        });

        // 4. Parameter Umum Tambahan (General Settings untuk Top-N, NPL min collectibility, dsb.)
        Schema::create('calculation_general_settings', function (Blueprint $table) {
            $table->id();
            $table->string('setting_key', 60)->unique();
            $table->string('setting_value', 255);
            $table->string('category', 30)->default('ckpn'); // ckpn | pd | lgd | general
            $table->string('label', 100);
            $table->string('description', 255)->nullable();
            $table->timestamps();
        });
    }
};
