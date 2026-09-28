<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabel penetapan periode penilaian CKPN.
 * Step 2 dari alur sistem — Ref: PRD Bab 12a
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ckpn_periods', function (Blueprint $table) {
            $table->id();
            $table->char('period', 6)->unique()->comment('yyyymm — periode yang dinilai');
            $table->string('status', 20)->default('draft')->comment('draft | in_progress | completed | approved');
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->text('notes')->nullable()->comment('Catatan approver');
            $table->timestamps();

            $table->index('status');
        });
    }
};
