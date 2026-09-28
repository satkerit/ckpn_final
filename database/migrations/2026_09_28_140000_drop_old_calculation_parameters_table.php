<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

/**
 * Hapus modul Parameter Kalkulasi lama (skema key-value generik).
 * Digantikan oleh skema terstruktur: calculation_column_configs, calculation_data_ranges,
 * calculation_segmentation_levels, dan calculation_general_settings.
 *
 * Ref: PRD Bab 15 + Permintaan User.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('calculation_parameters');
    }
};
