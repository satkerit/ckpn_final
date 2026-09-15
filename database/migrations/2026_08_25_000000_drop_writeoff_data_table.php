<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Hapus tabel writeoff_data — data writeoff sudah tersedia di financing_account_periods
 * (kolom writeoff_date, writeoff_status, outstanding_balance).
 * Ref: PRD Bab 15
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('writeoff_data');
    }

    public function down(): void
    {
        Schema::create('writeoff_data', function (Blueprint $table) {
            $table->id();
            $table->foreignId('financing_account_id')
                ->constrained('financing_accounts')
                ->onDelete('restrict');
            $table->date('writeoff_date');
            $table->decimal('writeoff_amount', 20, 2);
            $table->char('period', 6);
            $table->timestamps();

            $table->index('financing_account_id');
            $table->index('period');
        });
    }
};
