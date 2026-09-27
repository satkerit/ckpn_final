<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tambah kolom approval untuk workflow nilai estimasi agunan.
     * Ref: CKPN_AUDIT.md G-04 — estimasi agunan harus melalui approval
     */
    public function up(): void
    {
        Schema::table('collateral_sales_data', function ($table) {
            $table->string('approval_status', 20)->default('pending')->after('approved_by_user_id');
            $table->timestamp('approved_at')->nullable()->after('approval_status');
            $table->text('approval_notes')->nullable()->after('approved_at');

            // Index untuk filter berdasarkan status
            $table->index('approval_status', 'csd_approval_status_idx');
        });
    }

    public function down(): void
    {
        Schema::table('collateral_sales_data', function ($table) {
            $table->dropIndex('csd_approval_status_idx');
            $table->dropColumn(['approval_status', 'approved_at', 'approval_notes']);
        });
    }
};
