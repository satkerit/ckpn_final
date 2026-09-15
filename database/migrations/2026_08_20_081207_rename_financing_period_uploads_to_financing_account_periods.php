<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::rename('financing_period_uploads', 'financing_account_periods');
    }

    public function down(): void
    {
        Schema::rename('financing_account_periods', 'financing_period_uploads');
    }
};
