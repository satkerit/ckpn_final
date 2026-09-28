<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
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
};
