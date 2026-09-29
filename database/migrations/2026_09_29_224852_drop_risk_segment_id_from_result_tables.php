<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const TABLES = [
        'ckpn_collective_result',
        'lgd_expected_recoveries_result',
        'lgd_collateral_shortfall_result',
    ];

    public function up(): void
    {
        foreach (self::TABLES as $table) {
            if (!$this->tableHasColumn($table, 'risk_segment_id')) {
                continue;
            }

            // Use raw SQL to drop FK + column (bypass Laravel schema builder issues)
            try {
                DB::statement("ALTER TABLE `$table` DROP FOREIGN KEY `{$table}_risk_segment_id_foreign`");
            } catch (\Exception $e) {
                // FK name different, skip silently
            }

            try {
                DB::statement("ALTER TABLE `$table` DROP INDEX `{$table}_risk_segment_id_index`");
            } catch (\Exception $e) {
                // Index doesn't exist
            }

            DB::statement("ALTER TABLE `$table` DROP COLUMN `risk_segment_id`");
        }
    }

    public function down(): void
    {
        foreach (self::TABLES as $table) {
            if ($this->tableHasColumn($table, 'risk_segment_id')) {
                return;
            }

            DB::statement("ALTER TABLE `$table` ADD COLUMN `risk_segment_id` BIGINT UNSIGNED NULL");
            DB::statement("ALTER TABLE `$table` ADD CONSTRAINT `{$table}_risk_segment_id_foreign` FOREIGN KEY (`risk_segment_id`) REFERENCES `risk_segments` (`id`) ON DELETE RESTRICT");
            DB::statement("CREATE INDEX `{$table}_risk_segment_id_index` ON `$table` (`risk_segment_id`)");
        }
    }

    private function tableHasColumn(string $table, string $column): bool
    {
        return DB::selectOne("SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = ? AND COLUMN_NAME = ? AND TABLE_SCHEMA = ?", [$table, $column, DB::connection()->getDatabaseName()]) !== null;
    }
};
