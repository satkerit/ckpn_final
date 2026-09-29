<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('pd_netflow_result')) {
            return;
        }

        // Drop old unique constraint that references dropped risk_segment_id
        try {
            DB::statement('ALTER TABLE `pd_netflow_result` DROP INDEX `pd_nf_run_log_seg_bucket_unique`');
        } catch (\Exception) {
            // Index already dropped or doesn't exist
        }

        // Add new unique constraint: (run_log, usage_type, office_code, akad_code, from_bucket)
        try {
            DB::statement(
                'ALTER TABLE `pd_netflow_result` ADD UNIQUE KEY `pd_nf_run_log_usage_office_akad_bucket_unique` '
                . '(`calculation_run_log_id`, `usage_type`, `office_code`, `akad_code`, `from_bucket_id`)'
            );
        } catch (\Exception) {
            // Constraint already exists
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('pd_netflow_result')) {
            return;
        }

        try {
            DB::statement('ALTER TABLE `pd_netflow_result` DROP INDEX `pd_nf_run_log_usage_office_akad_bucket_unique`');
        } catch (\Exception) {
            // Index doesn't exist
        }

        try {
            DB::statement(
                'ALTER TABLE `pd_netflow_result` ADD UNIQUE KEY `pd_nf_run_log_seg_bucket_unique` '
                . '(`calculation_run_log_id`, `risk_segment_id`, `from_bucket_id`)'
            );
        } catch (\Exception) {
            // Constraint already exists or can't be recreated (risk_segment_id dropped)
        }
    }
};
