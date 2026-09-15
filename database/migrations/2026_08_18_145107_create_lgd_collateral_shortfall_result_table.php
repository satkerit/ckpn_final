<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lgd_collateral_shortfall_result', function (Blueprint $table) {
            $table->id();
            $table->foreignId('calculation_run_log_id')->constrained('calculation_run_log')->restrictOnDelete();
            $table->foreignId('financing_account_id')->constrained('financing_accounts')->restrictOnDelete();
            $table->foreignId('risk_segment_id')->nullable()->constrained('risk_segments')->restrictOnDelete();
            $table->char('calculation_period', 6)->notNull();
            $table->decimal('outstanding_balance', 20, 2)->notNull()->default(0);
            $table->decimal('collateral_net_value', 20, 2)->notNull()->default(0);
            $table->decimal('shortfall', 20, 2)->notNull()->default(0);
            $table->decimal('lgd_rate', 10, 8)->notNull()->default(0);
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['calculation_run_log_id', 'financing_account_id'], 'lgd_cs_run_log_acc_unique');
            $table->index('calculation_period');
            $table->index('risk_segment_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lgd_collateral_shortfall_result');
    }
};
