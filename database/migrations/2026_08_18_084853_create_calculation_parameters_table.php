<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('calculation_parameters', function (Blueprint $table) {
            $table->id();
            $table->foreignId('risk_segment_id')
                ->nullable()
                ->constrained('risk_segments')
                ->onDelete('restrict');
            $table->string('parameter_key', 100);
            $table->text('parameter_value');
            $table->text('description')->nullable();
            $table->timestamps();

            $table->unique(['risk_segment_id', 'parameter_key']);
            $table->index('parameter_key');
        });
    }
};
