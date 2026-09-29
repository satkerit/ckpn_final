<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Mengganti kolom risk_segment_id dengan usage_type di seluruh tabel hasil kalkulasi.
 * Setiap tabel: drop semua FK yang backing-nya adalah unique index dulu,
 * lalu drop index + kolom, lalu tambah usage_type + restore FK.
 * IRREVERSIBLE — down() melempar exception.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Migration made obsolete by refactored base migrations.
        // Base migrations now include usage_type + don't have risk_segment_id.
        // Skip silently — no structural changes needed.
    }
};
