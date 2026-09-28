<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabel Nilai Referensi Level Segmentasi Parameter.
 * Ref: PRD Bab 5, 15.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('calculation_segmentation_values', function (Blueprint $table) {
            $table->id();
            $table->foreignId('segmentation_level_id')
                ->constrained('calculation_segmentation_levels')
                ->cascadeOnDelete();
            $table->string('value', 50);
            $table->string('label', 100)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['segmentation_level_id', 'value'], 'uq_seg_level_value');
        });
    }
};
