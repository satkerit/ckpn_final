<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ckpn_individual_result', function (Blueprint $table): void {
            if (Schema::hasColumn('ckpn_individual_result', 'is_top_n_outstanding')) {
                $table->dropColumn('is_top_n_outstanding');
            }
        });
    }

    public function down(): void
    {
        Schema::table('ckpn_individual_result', function (Blueprint $table): void {
            $table->boolean('is_top_n_outstanding')->default(false);
        });
    }
};
