<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('collateral_sales_data', function (Blueprint $table) {
            $table->id();
            $table->foreignId('collateral_id')
                ->constrained('collaterals')
                ->onDelete('restrict');
            $table->date('sale_date');
            $table->decimal('sale_amount', 20, 2);
            $table->char('period', 6);
            $table->foreignId('approved_by_user_id')
                ->nullable()
                ->constrained('users')
                ->onDelete('restrict');
            $table->timestamps();

            $table->index('collateral_id');
            $table->index('period');
        });
    }
};
