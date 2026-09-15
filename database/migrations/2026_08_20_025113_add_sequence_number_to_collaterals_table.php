<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('collaterals', function (Blueprint $table) {
            // Nomor urut jaminan — 1 akun bisa punya >1 jaminan dengan collateral_code yang sama
            $table->unsignedSmallInteger('sequence_number')->default(1)->after('collateral_code');

            // Drop unique constraint lama (jika ada) dan buat yang baru dengan sequence_number
            $table->unique(['financing_account_id', 'collateral_code', 'sequence_number'], 'collaterals_account_code_seq_unique');
        });
    }

    public function down(): void
    {
        Schema::table('collaterals', function (Blueprint $table) {
            $table->dropUnique('collaterals_account_code_seq_unique');
            $table->dropColumn('sequence_number');
        });
    }
};
