<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Melengkapi kolom office_code pada tabel DETAIL PD Netflow:
 * - pd_netflow_bucket_movement
 * - pd_netflow_compound_rate
 * - pd_netflow_calculation_histories
 *
 * Tabel-tabel ini ditulis oleh SnapshotWriter::writePdNetflowDetail()/writePdNetflowHistory()
 * yang sudah menerima parameter office_code (segmentasi level 1 — Ref: PRD Bab 5).
 *
 * Migration IDEMPOTEN (guard hasColumn/hasIndex).
 */
return new class extends Migration
{
    /** @var array<string, string> tabel => kolom setelahnya */
    private const TABLES = [
        'pd_netflow_bucket_movement' => 'usage_type',
        'pd_netflow_compound_rate' => 'usage_type',
        'pd_netflow_calculation_histories' => 'usage_type',
    ];

    public function up(): void
    {
        foreach (self::TABLES as $table => $after) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            if (! Schema::hasColumn($table, 'office_code')) {
                Schema::table($table, function (Blueprint $blueprint) use ($after): void {
                    $blueprint->string('office_code', 10)->nullable()->after($after);
                });
            }

            $indexName = $table.'_office_code_index';
            if (! Schema::hasIndex($table, $indexName)) {
                Schema::table($table, function (Blueprint $blueprint) use ($indexName): void {
                    $blueprint->index('office_code', $indexName);
                });
            }
        }
    }

    public function down(): void
    {
        // IRREVERSIBLE — Ref: AGENTS.md §9.
    }
};
