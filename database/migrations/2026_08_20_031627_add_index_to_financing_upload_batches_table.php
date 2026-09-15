<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('financing_upload_batches', function (Blueprint $table) {
            // Composite index untuk query getLastUploadPerType()
            // SELECT * WHERE upload_type = ? ORDER BY uploaded_at DESC LIMIT 1
            $table->index(['upload_type', 'uploaded_at'], 'idx_upload_type_uploaded_at');
        });
    }

    public function down(): void
    {
        Schema::table('financing_upload_batches', function (Blueprint $table) {
            $table->dropIndex('idx_upload_type_uploaded_at');
        });
    }
};
