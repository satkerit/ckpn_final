<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('financing_outstanding_monthly', function (Blueprint $table) {
            $table->id();
            $table->foreignId('risk_segment_id')
                ->constrained('risk_segments')
                ->onDelete('restrict');
            $table->foreignId('bucket_id')
                ->constrained('buckets')
                ->onDelete('restrict');
            $table->char('period', 6);
            $table->decimal('total_outstanding', 20, 2)->default(0);
            $table->integer('account_count')->default(0);
            $table->timestamps();

            $table->unique(['risk_segment_id', 'bucket_id', 'period'], 'fom_seg_bucket_period_unique');
            $table->index('period');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('financing_outstanding_monthly');
    }
};
