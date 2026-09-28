<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('collaterals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('financing_account_id')
                ->constrained('financing_accounts')
                ->onDelete('restrict');
            $table->foreignId('collateral_type_id')
                ->constrained('collateral_types')
                ->onDelete('restrict');
            $table->string('collateral_code', 50)->nullable();
            $table->text('description')->nullable();
            $table->decimal('appraisal_value', 20, 2)->nullable();
            $table->decimal('estimated_sale_value', 20, 2)->nullable();
            $table->date('appraised_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index('financing_account_id');
        });
    }
};
