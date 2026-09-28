<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('writeoff_data', function (Blueprint $table) {
            $table->id();
            $table->foreignId('financing_account_id')
                ->constrained('financing_accounts')
                ->onDelete('restrict');
            $table->date('writeoff_date');
            $table->decimal('writeoff_amount', 20, 2);
            $table->char('period', 6);
            $table->timestamps();

            $table->index('financing_account_id');
            $table->index('period');
        });
    }
};
