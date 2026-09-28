<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('financing_period_uploads', function (Blueprint $table) {
            $table->unsignedSmallInteger('tgkhari')->nullable()->after('collectibility')
                ->comment('Hari tunggakan / days past due');
            $table->index('tgkhari');
        });
    }
};
