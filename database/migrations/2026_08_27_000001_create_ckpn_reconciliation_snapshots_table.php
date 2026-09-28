<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ckpn_reconciliation_snapshots', function (Blueprint $table): void {
            $table->id();
            $table->char('period', 6); // yyyymm
            $table->string('usage_type', 50)->nullable(); // null = konsolidasi semua
            $table->string('classification', 20); // 'individual' | 'collective'
            $table->unsignedInteger('count_classification')->default(0); // jumlah nasabah saat klasifikasi
            $table->decimal('total_ead_classification', 20, 2)->default(0); // total EAD saat klasifikasi
            $table->unsignedInteger('count_ckpn_result')->default(0); // jumlah baris di hasil CKPN
            $table->decimal('total_ead_ckpn_result', 20, 2)->default(0); // total EAD di hasil CKPN
            $table->boolean('is_count_match')->default(false);
            $table->boolean('is_ead_match')->default(false);
            $table->timestamp('checked_at')->useCurrent();
            $table->unique(['period', 'usage_type', 'classification'], 'uq_recon_period_type_class');
        });
    }
};
