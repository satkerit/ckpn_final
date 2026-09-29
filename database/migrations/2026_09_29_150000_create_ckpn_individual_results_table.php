<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ckpn_individual_results', function (Blueprint $table) {
            $table->id();
            $table->foreignId('calculation_run_log_id')->constrained('calculation_run_log')->restrictOnDelete();
            $table->string('account_number', 50)->notNull()->index();
            $table->unsignedTinyInteger('usage_type')->notNull();
            $table->string('office_code', 10)->nullable();
            $table->string('akad_code', 10)->nullable();
            $table->char('calculation_period', 6)->notNull();
            $table->unsignedTinyInteger('bucket')->notNull();
            $table->unsignedSmallInteger('days_past_due')->notNull()->default(0);
            $table->decimal('pd_rate', 10, 8)->notNull()->default(0);
            $table->decimal('lgd_rate', 10, 8)->notNull()->default(0);
            $table->decimal('ckpn_rate', 10, 8)->notNull()->default(0);
            $table->decimal('outstanding', 20, 2)->notNull()->default(0);
            $table->decimal('ckpn_amount', 20, 2)->notNull()->default(0);
            $table->text('notes')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['calculation_run_log_id', 'account_number'], 'ckpn_ind_run_log_account_unique');
            $table->index('calculation_period');
            $table->index('usage_type');
            $table->index(['calculation_period', 'usage_type']);
        });
    }
};
