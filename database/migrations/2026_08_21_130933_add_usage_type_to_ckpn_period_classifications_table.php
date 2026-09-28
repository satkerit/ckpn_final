<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tambah kolom usage_type ke ckpn_period_classifications.
 * Diambil dari financing_accounts.usage_type saat populate.
 * Ref: PRD Bab 6.1
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ckpn_period_classifications', function (Blueprint $table) {
            $table->unsignedTinyInteger('usage_type')
                ->nullable()
                ->after('writeoff_status')
                ->comment('1=Modal Kerja, 2=Investasi, 3=Konsumsi — dari financing_accounts.usage_type');
        });
    }
};
