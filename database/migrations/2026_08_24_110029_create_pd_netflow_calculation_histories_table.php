<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('pd_netflow_calculation_histories')) {
            Schema::table('pd_netflow_calculation_histories', function (Blueprint $table): void {
                $table->unique(
                    ['calculation_run_log_id', 'usage_type'],
                    'pd_nf_history_run_usage_unique',
                );
                $table->index('calculation_period', 'pd_nf_history_period_index');
            });

            return;
        }

        Schema::create('pd_netflow_calculation_histories', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('calculation_run_log_id')->constrained('calculation_run_log')->cascadeOnDelete();
            $table->char('calculation_period', 6);
            $table->unsignedTinyInteger('usage_type')->nullable();
            $table->json('history_data');
            $table->timestamps();
            $table->unique(
                ['calculation_run_log_id', 'usage_type'],
                'pd_nf_history_run_usage_unique',
            );
            $table->index('calculation_period');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pd_netflow_calculation_histories');
    }
};
