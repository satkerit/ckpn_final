<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('calculation_segmentation_configs', function (Blueprint $table) {
            $table->id();
            $table->string('method', 60); // pd_netflow, pd_migration, lgd_er, lgd_cs, ckpn_individual, ckpn_collective
            $table->json('segment_dimensions'); // ['office_code', 'akad_code'] atau ['usage_type'] dll
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['method']);
            $table->index('is_active');
        });
    }
};
