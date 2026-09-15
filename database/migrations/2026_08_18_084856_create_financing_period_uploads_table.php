<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('financing_period_uploads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('financing_account_id')
                ->constrained('financing_accounts')
                ->onDelete('restrict');
            $table->char('period', 6);
            $table->decimal('outstanding_balance', 20, 2)->default(0);
            $table->tinyInteger('collectibility');
            $table->date('writeoff_date')->nullable();
            $table->string('financing_status', 5);
            $table->string('writeoff_status', 5)->nullable();
            $table->foreignId('upload_batch_id')
                ->constrained('financing_upload_batches')
                ->onDelete('restrict');
            $table->timestamps();

            $table->unique(['financing_account_id', 'period']);
            $table->index(['period', 'collectibility']);
            $table->index(['period', 'writeoff_status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('financing_period_uploads');
    }
};
