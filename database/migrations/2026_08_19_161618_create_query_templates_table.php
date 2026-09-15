<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('query_templates', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('upload_type'); // master, collateral, period, office, collateral_type
            $table->string('source_database')->default('sqlserver'); // sqlserver / oracle / etc
            $table->text('description')->nullable();
            $table->longText('sql_query');
            $table->json('parameters')->nullable(); // parameter yang bisa diubah user, mis. {period: '202506'}
            $table->string('target_table')->nullable(); // tabel MySQL tujuan
            $table->boolean('is_active')->default(true);
            $table->unsignedBigInteger('created_by_user_id')->nullable();
            $table->unsignedBigInteger('updated_by_user_id')->nullable();
            $table->timestamps();

            $table->foreign('created_by_user_id')->references('id')->on('users')->nullOnDelete();
            $table->foreign('updated_by_user_id')->references('id')->on('users')->nullOnDelete();

            $table->index('upload_type');
            $table->index('is_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('query_templates');
    }
};
