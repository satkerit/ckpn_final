<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('calculation_run_log', function (Blueprint $table) {
            // Hapus foreign key dan kolom risk_segment_id yang tidak dipakai lagi
            // usage_type sudah ada di tabel (ditambahkan via migration lain)
            if (Schema::hasColumn('calculation_run_log', 'risk_segment_id')) {
                $table->dropForeign(['risk_segment_id']);
                $table->dropColumn('risk_segment_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('calculation_run_log', function (Blueprint $table) {
            $table->dropColumn('usage_type');

            $table->foreignId('risk_segment_id')
                ->nullable()
                ->constrained('risk_segments')
                ->onDelete('restrict');
        });
    }
};
