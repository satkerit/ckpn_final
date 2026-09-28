<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('financing_accounts', function (Blueprint $table) {
            $table->id();
            $table->string('account_number', 50)->unique();
            $table->string('product_code', 20)->nullable();
            $table->string('akad_code', 20)->nullable();
            $table->string('office_code', 20)->nullable();
            $table->string('economic_sector', 100)->nullable();
            $table->tinyInteger('usage_type')->nullable();
            $table->date('origination_date')->nullable();
            $table->date('maturity_date')->nullable();
            $table->string('customer_name', 150)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index('office_code');
            $table->index('usage_type');
        });
    }
};
