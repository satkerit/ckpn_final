<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tambah kolom justification_notes & recommendation_values ke calculation_data_ranges
 * untuk mendukung window range guidance & audit trail.
 * Ref: IMPROVEMENT_PLAN_PD_CALCULATION.md Phase 1
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('calculation_data_ranges', function (Blueprint $table) {
            $table->text('justification_notes')->nullable()->after('notes')
                ->comment('Alasan perubahan range (wajib jika berbeda dari default)');
            $table->string('recommendation_values', 255)->nullable()->after('justification_notes')
                ->comment('Nilai rekomendasi (CSV: 12,24,36,48,60)');
        });
    }

    public function down(): void
    {
        Schema::table('calculation_data_ranges', function (Blueprint $table) {
            $table->dropColumn(['justification_notes', 'recommendation_values']);
        });
    }
};
