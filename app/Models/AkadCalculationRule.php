<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Master rule akad untuk pemilihan field perhitungan PD/CKPN.
 * Ref: PRD Bab 5, 7, 8, User requirement #5
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
            'use_field' => 'string', // 'outstanding_balance' | 'tgkmdl'
            'is_active' => 'boolean',
        ];
    }

    /**
     * Scope: hanya rule yang aktif.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Get field name yang dipakai untuk akad ini.
     */
    public function getUseField(): string
    {
        return $this->use_field ?? 'outstanding_balance';
    }

    /**
     * Cek apakah akad ini pakai tunggakan pokok.
     */
    public function usesTunggakanPokok(): bool
    {
        return $this->getUseField() === 'tgkmdl';
    }
}