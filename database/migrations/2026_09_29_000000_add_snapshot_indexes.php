<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // PD Netflow indexes
        Schema::table('pd_netflow_result', function (Blueprint $table) {
            $table->index(['calculation_period', 'usage_type', 'office_code'], 'idx_pd_netflow_period_usage_office');
            $table->index(['calculation_period', 'usage_type', 'from_bucket_id'], 'idx_pd_netflow_period_usage_bucket');
            $table->index(['calculation_run_log_id'], 'idx_pd_netflow_runlog');
        });

        // PD Migration indexes
        Schema::table('pd_migration_result', function (Blueprint $table) {
            $table->index(['calculation_period', 'office_code'], 'idx_pd_migration_period_office');
            $table->index(['calculation_run_log_id'], 'idx_pd_migration_runlog');
        });

        // LGD Expected Recoveries indexes
        Schema::table('lgd_expected_recoveries_result', function (Blueprint $table) {
            $table->index(['calculation_period', 'usage_type', 'office_code'], 'idx_lgd_er_period_usage_office');
            $table->index(['calculation_run_log_id'], 'idx_lgd_er_runlog');
        });

        // LGD Collateral Shortfall indexes
        Schema::table('lgd_collateral_shortfall_result', function (Blueprint $table) {
            $table->index(['calculation_period', 'usage_type', 'office_code'], 'idx_lgd_cs_period_usage_office');
            $table->index(['calculation_run_log_id'], 'idx_lgd_cs_runlog');
        });

        // CKPN Individual indexes
        Schema::table('ckpn_individual_result', function (Blueprint $table) {
            $table->index(['calculation_period', 'financing_account_id'], 'idx_ckpn_indiv_period_account');
            $table->index(['calculation_run_log_id'], 'idx_ckpn_indiv_runlog');
        });

        // CKPN Collective indexes
        Schema::table('ckpn_collective_result', function (Blueprint $table) {
            $table->index(['calculation_period', 'office_code'], 'idx_ckpn_coll_period_office');
            $table->index(['calculation_run_log_id'], 'idx_ckpn_coll_runlog');
        });

        // Calculation Run Log indexes
        Schema::table('calculation_run_log', function (Blueprint $table) {
            $table->index(['period', 'status'], 'idx_runlog_period_status');
            $table->index(['triggered_by_user_id'], 'idx_runlog_user');
        });
    }

    public function down(): void
    {
        Schema::table('pd_netflow_result', function (Blueprint $table) {
            $table->dropIndex('idx_pd_netflow_period_usage_office');
            $table->dropIndex('idx_pd_netflow_period_usage_bucket');
            $table->dropIndex('idx_pd_netflow_runlog');
        });

        Schema::table('pd_migration_result', function (Blueprint $table) {
            $table->dropIndex('idx_pd_migration_period_office');
            $table->dropIndex('idx_pd_migration_runlog');
        });

        Schema::table('lgd_expected_recoveries_result', function (Blueprint $table) {
            $table->dropIndex('idx_lgd_er_period_usage_office');
            $table->dropIndex('idx_lgd_er_runlog');
        });

        Schema::table('lgd_collateral_shortfall_result', function (Blueprint $table) {
            $table->dropIndex('idx_lgd_cs_period_usage_office');
            $table->dropIndex('idx_lgd_cs_runlog');
        });

        Schema::table('ckpn_individual_result', function (Blueprint $table) {
            $table->dropIndex('idx_ckpn_indiv_period_account');
            $table->dropIndex('idx_ckpn_indiv_runlog');
        });

        Schema::table('ckpn_collective_result', function (Blueprint $table) {
            $table->dropIndex('idx_ckpn_coll_period_office');
            $table->dropIndex('idx_ckpn_coll_runlog');
        });

        Schema::table('calculation_run_log', function (Blueprint $table) {
            $table->dropIndex('idx_runlog_period_status');
            $table->dropIndex('idx_runlog_user');
        });
    }
};
