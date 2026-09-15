<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\FinancingOfficeFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FinancingOffice extends Model
{
    /** @use HasFactory<FinancingOfficeFactory> */
    use HasFactory;

    protected $table = 'financing_offices';

    protected $fillable = [
        'code',
        'name',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }
}
