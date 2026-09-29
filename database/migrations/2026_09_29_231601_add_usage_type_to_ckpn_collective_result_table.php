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
        Schema::table('ckpn_collective_result', function (Blueprint $table) {
            if (! Schema::hasColumn('ckpn_collective_result', 'usage_type')) {
                $table->unsignedTinyInteger('usage_type')->nullable()->after('financing_account_id');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ckpn_collective_result', function (Blueprint $table) {
            $table->dropColumn('usage_type');
        });
    }
};
