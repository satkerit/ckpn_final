<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('financing_upload_batches', function (Blueprint $table) {
            $table->id();
            $table->char('period', 6);
            $table->string('filename', 255);
            $table->foreignId('uploaded_by_user_id')
                ->constrained('users')
                ->onDelete('restrict');
            $table->timestamp('uploaded_at')->useCurrent();
            $table->integer('total_rows')->default(0);
            $table->integer('imported_rows')->default(0);
            $table->integer('failed_rows')->default(0);
            $table->string('status', 20)->default('pending');
            $table->json('error_summary')->nullable();
            $table->timestamps();

            $table->index('period');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('financing_upload_batches');
    }
};
