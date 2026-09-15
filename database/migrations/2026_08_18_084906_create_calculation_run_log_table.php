<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('calculation_run_log', function (Blueprint $table) {
            $table->id();
            $table->char('period', 6);
            $table->string('run_type', 50);
            $table->foreignId('risk_segment_id')
                ->nullable()
                ->constrained('risk_segments')
                ->onDelete('restrict');
            $table->string('status', 20)->default('pending');
            $table->foreignId('triggered_by_user_id')
                ->nullable()
                ->constrained('users')
                ->onDelete('restrict');
            $table->foreignId('approved_by_user_id')
                ->nullable()
                ->constrained('users')
                ->onDelete('restrict');
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->text('notes')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();

            $table->index(['period', 'run_type']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('calculation_run_log');
    }
};
