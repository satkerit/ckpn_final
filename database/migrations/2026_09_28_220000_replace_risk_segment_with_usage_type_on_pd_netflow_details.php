<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Menyamakan skema tabel detail PD Netflow dengan skema tabel hasil lain:
 * kolom risk_segment_id (sisa skema lama) diganti usage_type.
 *
 * Root cause bug: PdNetflowBucketMovement & PdNetflowCompoundRate menulis kolom
 * `usage_type` (lihat SnapshotWriter::writePdNetflowDetail) sementara tabelnya masih
 * punya `risk_segment_id` → job PD Netflow gagal dengan
 * "Unknown column 'usage_type' in 'field list'".
 *
 * Catatan:
 * - Kolom dibuat NULLABLE agar baris hasil lama tetap valid; run baru mengisi nilai segmen.
 * - Index (run_log, bucket, period) dan (run_log, bucket, start_period) dipertahankan
 *   demi performa query export/detail — tanpa unique agar retry job tidak bentrok.
 *
 * Ref: PRD Bab 7, 15; AGENTS.md §4 (snapshot).
 */
return new class extends Migration
{
    public function up(): void
    {
        // ── pd_netflow_bucket_movement ──────────────────────────────────────
        Schema::table('pd_netflow_bucket_movement', function (Blueprint $table): void {
            $table->dropForeign(['risk_segment_id']);
            $table->dropIndex('pd_nfbm_run_log_seg_bucket_period_unique');
            $table->dropColumn('risk_segment_id');
        });

        Schema::table('pd_netflow_bucket_movement', function (Blueprint $table): void {
            $table->unsignedTinyInteger('usage_type')->nullable()->after('calculation_run_log_id');
            $table->index(['calculation_run_log_id', 'from_bucket_id', 'period'], 'pd_nfbm_run_bucket_period_index');
            $table->index('usage_type');
        });

        // ── pd_netflow_compound_rate ────────────────────────────────────────
        Schema::table('pd_netflow_compound_rate', function (Blueprint $table): void {
            $table->dropForeign(['risk_segment_id']);
            $table->dropIndex('pd_nfc_run_log_seg_bucket_period_unique');
            $table->dropColumn('risk_segment_id');
        });

        Schema::table('pd_netflow_compound_rate', function (Blueprint $table): void {
            $table->unsignedTinyInteger('usage_type')->nullable()->after('calculation_run_log_id');
            $table->index(['calculation_run_log_id', 'from_bucket_id', 'start_period'], 'pd_nfc_run_bucket_start_index');
            $table->index('usage_type');
        });
    }

    public function down(): void
    {
        // IRREVERSIBLE — skema lama (risk_segment_id) sudah tidak dipakai model mana pun.
    }
};
