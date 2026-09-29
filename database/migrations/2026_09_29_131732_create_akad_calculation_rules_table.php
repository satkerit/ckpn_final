<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Master rule akad untuk pemilihan field perhitungan PD/CKPN.
 * Setiap akad bisa pakai outstanding_balance ATAU tgkmdl (tunggakan pokok).
 * Default = outstanding_balance.
 * Ref: PRD Bab 5, 7, 8, User requirement #5
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('akad_calculation_rules', function (Blueprint $table) {
            $table->id();

            $table->string('akad_code', 10)->unique()
                ->comment('Kode akad dari master data (mis: 01, 02, 03, 04, 05, dll)');

            $table->string('akad_name', 100)->nullable()
                ->comment('Nama akad untuk referensi (mis: Murabahah, Musyarakah, dll)');

            $table->enum('use_field', ['outstanding_balance', 'tgkmdl'])
                ->default('outstanding_balance')
                ->comment('Field yang dipakai untuk perhitungan PD/CKPN: outstanding_balance (default) atau tgkmdl (tunggakan pokok)');

            $table->text('description')->nullable()
                ->comment('Catatan / alasan pemilihan field ini untuk akad tsb');

            $table->boolean('is_active')
                ->default(true)
                ->comment('Apakah rule ini aktif dipakai');

            $table->timestamps();

            $table->index('akad_code');
        });
    }
};
