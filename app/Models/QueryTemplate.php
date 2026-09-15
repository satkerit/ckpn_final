<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Model untuk menyimpan template query SQL Server yang digunakan untuk menarik data ke MySQL.
 * Ref: fitur Query Wizard - Upload Data
 */
class QueryTemplate extends Model
{
    protected $table = 'query_templates';

    protected $fillable = [
        'name',
        'upload_type',
        'source_database',
        'description',
        'sql_query',
        'parameters',
        'target_table',
        'is_active',
        'created_by_user_id',
        'updated_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'parameters' => 'array',
            'is_active' => 'boolean',
        ];
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by_user_id');
    }

    /** Label tipe upload yang lebih mudah dibaca. */
    public function getUploadTypeLabelAttribute(): string
    {
        return match ($this->upload_type) {
            'master' => 'Master Pembiayaan',
            'collateral' => 'Data Jaminan',
            'period' => 'Historis Pembiayaan',
            'office' => 'Master Kantor',
            'collateral_type' => 'Master Jenis Jaminan',
            default => ucfirst($this->upload_type),
        };
    }
}
