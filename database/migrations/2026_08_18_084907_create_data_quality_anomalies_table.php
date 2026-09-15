<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('data_quality_anomalies', function (Blueprint $table) {
            $table->id();
            $table->char('period', 6);
            $table->foreignId('risk_segment_id')
                ->nullable()
                ->constrained('risk_segments')
                ->onDelete('restrict');
            $table->foreignId('bucket_id')
                ->nullable()
                ->constrained('buckets')
                ->onDelete('restrict');
            $table->string('anomaly_type', 50);
            $table->text('description')->nullable();
            $table->boolean('is_resolved')->default(false);
            $table->foreignId('resolved_by_user_id')
                ->nullable()
                ->constrained('users')
                ->onDelete('restrict');
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            $table->index('period');
            $table->index('anomaly_type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('data_quality_anomalies');
    }
};
