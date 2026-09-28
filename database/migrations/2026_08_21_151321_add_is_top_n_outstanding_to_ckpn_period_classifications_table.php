<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tambah kolom is_top_n_outstanding ke ckpn_period_classifications.
 * Diisi oleh staging job saat klasifikasi — kalkulator hanya membaca.
 * Ref: PRD Bab 6.1
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ckpn_period_classifications', function (Blueprint $table) {
            $table->boolean('is_top_n_outstanding')
                ->default(false)
                ->after('usage_type')
                ->comment('true = masuk top-N outstanding terbesar, ditentukan staging job');
        });
    }
};
