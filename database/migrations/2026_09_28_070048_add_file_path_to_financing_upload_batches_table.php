<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Menyimpan path file upload (relatif terhadap disk "local").
 * Dipakai agar proses impor membaca path dari server, bukan dari input client.
 *
 * Ref: PRD Bab 3 - Upload Data Pembiayaan.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('financing_upload_batches', function (Blueprint $table) {
            $table->string('file_path', 255)->nullable()->after('filename');
        });
    }
};
