<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('financing_upload_batches', function (Blueprint $table) {
            $table->integer('skipped_rows')->default(0)->after('failed_rows');
            $table->integer('processed_rows')->default(0)->after('skipped_rows');
            $table->json('progress_log')->nullable()->after('error_summary');
        });
    }

    public function down(): void
    {
        Schema::table('financing_upload_batches', function (Blueprint $table) {
            $table->dropColumn(['skipped_rows', 'processed_rows', 'progress_log']);
        });
    }
};
