<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('export_jobs', function (Blueprint $table): void {
            $table->id();
            $table->string('type', 50);           // mis. pd_netflow_pivot
            $table->string('status', 20)->default('pending'); // pending|processing|done|failed
            $table->string('filename')->nullable();
            $table->string('file_path')->nullable();
            $table->text('error_message')->nullable();
            $table->json('params')->nullable();   // parameter export (periode, usage_type, dst)
            $table->timestamps();

            $table->index(['type', 'status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('export_jobs');
    }
};
