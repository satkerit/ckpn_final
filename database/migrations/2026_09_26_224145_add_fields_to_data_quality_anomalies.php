<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Update data_quality_anomalies dengan kolom baru untuk workflow review.
     * Ref: CKPN_AUDIT.md G-03 — UI untuk review anomali data quality
     */
    public function up(): void
    {
        Schema::table('data_quality_anomalies', function ($table) {
            // Tambah kolom baru (jika belum ada)
            if (! Schema::hasColumn('data_quality_anomalies', 'severity')) {
                $table->string('severity', 20)->default('warning')->after('description');
            }
            if (! Schema::hasColumn('data_quality_anomalies', 'status')) {
                $table->string('status', 20)->default('pending')->after('severity');
            }
            if (! Schema::hasColumn('data_quality_anomalies', 'reviewed_by')) {
                $table->unsignedBigInteger('reviewed_by')->nullable()->after('status');
            }
            if (! Schema::hasColumn('data_quality_anomalies', 'reviewed_at')) {
                $table->timestamp('reviewed_at')->nullable()->after('reviewed_by');
            }
            if (! Schema::hasColumn('data_quality_anomalies', 'review_notes')) {
                $table->text('review_notes')->nullable()->after('reviewed_at');
            }
            if (! Schema::hasColumn('data_quality_anomalies', 'source_table')) {
                $table->string('source_table', 100)->nullable()->after('review_notes');
            }

            // Index untuk filter (jika belum ada)
            try {
                $table->index(['period', 'status'], 'dqa_period_status_idx');
            } catch (Exception $e) {
                // Index mungkin sudah ada
            }
            try {
                $table->index(['period', 'severity'], 'dqa_period_severity_idx');
            } catch (Exception $e) {
                // Index mungkin sudah ada
            }
        });
    }
};
