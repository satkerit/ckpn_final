<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Master rule akad untuk pemilihan field perhitungan PD/CKPN.
 * Ref: PRD Bab 5, 7, User requirement #5
 */
class AkadCalculationRule extends Model
{
    use HasFactory;

    protected $table = 'akad_calculation_rules';

    protected $fillable = [
        'akad_code',
        'akad_name',
        'use_field',
        'description',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }
}
