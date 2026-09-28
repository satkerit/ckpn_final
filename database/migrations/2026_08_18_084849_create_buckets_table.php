<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('buckets', function (Blueprint $table) {
            $table->id();
            $table->string('code', 10)->unique();
            $table->string('label', 50);
            $table->integer('min_days_overdue')->nullable();
            $table->integer('max_days_overdue')->nullable();
            $table->tinyInteger('bucket_order');
            $table->boolean('is_default_bucket')->default(false);
            $table->timestamps();
        });
    }
};
