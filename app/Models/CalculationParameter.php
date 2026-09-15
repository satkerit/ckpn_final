<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\UsageType;
use Database\Factories\CalculationParameterFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CalculationParameter extends Model
{
    /** @use HasFactory<CalculationParameterFactory> */
    use HasFactory;

    protected $table = 'calculation_parameters';

    protected $fillable = [
        'usage_type',
        'parameter_key',
        'parameter_value',
        'description',
    ];

    protected function casts(): array
    {
        return [
            'usage_type' => UsageType::class,
        ];
    }

    /**
     * Ambil nilai parameter global (usage_type = null) berdasarkan key.
     * Kembalikan $default jika tidak ditemukan.
     */
    public static function getValue(string $key, string $default = ''): string
    {
        return (string) (static::where('parameter_key', $key)
            ->whereNull('usage_type')
            ->value('parameter_value') ?? $default);
    }
}
