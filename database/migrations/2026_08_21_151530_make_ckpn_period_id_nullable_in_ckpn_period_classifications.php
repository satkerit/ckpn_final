<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Jadikan ckpn_period_id nullable agar staging data bisa diinsert
 * sebelum CkpnPeriod dibuat.
 * Ref: PRD Bab 6.1, 12a Step 2
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ckpn_period_classifications', function (Blueprint $table) {
            // Drop FK dulu sebelum ubah kolom
            $table->dropForeign(['ckpn_period_id']);
            $table->unsignedBigInteger('ckpn_period_id')->nullable()->change();
            $table->foreign('ckpn_period_id')
                ->references('id')
                ->on('ckpn_periods')
                ->onDelete('restrict');
        });
    }

    public function down(): void
    {
        Schema::table('ckpn_period_classifications', function (Blueprint $table) {
            $table->dropForeign(['ckpn_period_id']);
            $table->unsignedBigInteger('ckpn_period_id')->nullable(false)->change();
            $table->foreign('ckpn_period_id')
                ->references('id')
                ->on('ckpn_periods')
                ->onDelete('restrict');
        });
    }
};
