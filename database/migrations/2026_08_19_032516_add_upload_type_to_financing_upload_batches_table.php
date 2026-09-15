<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('financing_upload_batches', function (Blueprint $table) {
            $table->string('upload_type', 30)->default('period')->after('period');
            $table->index('upload_type');
        });
    }

    public function down(): void
    {
        Schema::table('financing_upload_batches', function (Blueprint $table) {
            $table->dropIndex(['upload_type']);
            $table->dropColumn('upload_type');
        });
    }
};
