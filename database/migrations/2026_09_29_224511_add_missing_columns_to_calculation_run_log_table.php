<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('calculation_run_log', function (Blueprint $table) {
            if (!Schema::hasColumn('calculation_run_log', 'usage_type')) {
                $table->unsignedTinyInteger('usage_type')->nullable()->after('run_type');
            }
            if (!Schema::hasColumn('calculation_run_log', 'office_code')) {
                $table->string('office_code', 10)->nullable()->after('usage_type');
            }
            if (!Schema::hasColumn('calculation_run_log', 'akad_code')) {
                $table->string('akad_code', 20)->nullable()->after('office_code');
            }
        });
    }

    public function down(): void
    {
        Schema::table('calculation_run_log', function (Blueprint $table) {
            if (Schema::hasColumn('calculation_run_log', 'usage_type')) {
                $table->dropColumn('usage_type');
            }
            if (Schema::hasColumn('calculation_run_log', 'office_code')) {
                $table->dropColumn('office_code');
            }
            if (Schema::hasColumn('calculation_run_log', 'akad_code')) {
                $table->dropColumn('akad_code');
            }
        });
    }
};
