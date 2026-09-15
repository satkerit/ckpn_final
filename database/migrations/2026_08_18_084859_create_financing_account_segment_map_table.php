<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('financing_account_segment_map', function (Blueprint $table) {
            $table->id();
            $table->foreignId('financing_account_id')
                ->constrained('financing_accounts')
                ->onDelete('restrict');
            $table->foreignId('risk_segment_id')
                ->constrained('risk_segments')
                ->onDelete('restrict');
            $table->char('effective_from', 6);
            $table->char('effective_to', 6)->nullable();
            $table->timestamps();

            $table->index(['financing_account_id', 'effective_from'], 'fas_map_acc_eff_idx');
            $table->index('risk_segment_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('financing_account_segment_map');
    }
};
