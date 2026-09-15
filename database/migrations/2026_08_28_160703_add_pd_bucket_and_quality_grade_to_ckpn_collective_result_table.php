<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tambah kolom pd_bucket_id dan pd_quality_grade_id ke ckpn_collective_result.
 * - pd_bucket_id     : diisi saat pdMethod='netflow', FK ke tabel buckets
 * - pd_quality_grade_id: diisi saat pdMethod='migration', kolektibilitas 1-5
 * Ditampilkan secara dinamis di UI berdasarkan pd_method_used.
 * Ref: PRD Bab 11
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ckpn_collective_result', function (Blueprint $table) {
            $table->unsignedBigInteger('pd_bucket_id')
                ->nullable()
                ->after('ead')
                ->comment('Bucket yang dipakai saat PD method=netflow (FK ke buckets.id)');

            $table->unsignedTinyInteger('pd_quality_grade_id')
                ->nullable()
                ->after('pd_bucket_id')
                ->comment('Quality grade yang dipakai saat PD method=migration (1=Lancar..5=Macet)');

            $table->foreign('pd_bucket_id')->references('id')->on('buckets')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('ckpn_collective_result', function (Blueprint $table) {
            $table->dropForeign(['pd_bucket_id']);
            $table->dropColumn(['pd_bucket_id', 'pd_quality_grade_id']);
        });
    }
};
