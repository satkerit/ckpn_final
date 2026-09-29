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
        Schema::table('financing_account_periods', function (Blueprint $table) {
            if (! Schema::hasColumn('financing_account_periods', 'office_code')) {
                $table->string('office_code', 10)->nullable()->after('upload_batch_id');
            }
            if (! Schema::hasColumn('financing_account_periods', 'akad_code')) {
                $table->string('akad_code', 20)->nullable()->after('office_code');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('financing_account_periods', function (Blueprint $table) {
            $table->dropColumn(['office_code', 'akad_code']);
        });
    }
};
