<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pd_netflow_result', function (Blueprint $table) {
            // Drop FK before dropping column
            if (Schema::hasColumn('pd_netflow_result', 'risk_segment_id')) {
                $table->dropForeign(['risk_segment_id']);
                $table->dropIndex('pd_netflow_result_risk_segment_id_index');
                $table->dropColumn('risk_segment_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('pd_netflow_result', function (Blueprint $table) {
            $table->foreignId('risk_segment_id')->constrained('risk_segments')->restrictOnDelete();
            $table->index('risk_segment_id');
        });
    }
};
