<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\CalculationGeneralSettingFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Parameter umum berskala tunggal (mis. Top-N CKPN Individual, batas NPL,
 * biaya penjualan jaminan, metode PD CKPN Kolektif).
 *
 * Ref: PRD Bab 6.1, 11.
 *
 * @property int $id
 * @property string $setting_key
 * @property string $setting_value
 * @property string $category
 * @property string $label
 * @property string|null $description
 */
class CalculationGeneralSetting extends Model
{
    /** @use HasFactory<CalculationGeneralSettingFactory> */
    use HasFactory;

    protected $table = 'calculation_general_settings';

    protected $fillable = [
        'setting_key',
        'setting_value',
        'category',
        'label',
        'description',
    ];

    /**
     * Ambil nilai setting sebagai string. Kembalikan $default jika tidak ada.
     */
    public static function value(string $key, string $default = ''): string
    {
        $value = static::query()->where('setting_key', $key)->value('setting_value');

        return $value !== null && trim((string) $value) !== '' ? (string) $value : $default;
    }

    /**
     * Ambil nilai setting sebagai integer.
     */
    public static function intValue(string $key, int $default = 0): int
    {
        $value = static::value($key);

        return $value !== '' ? (int) $value : $default;
    }

    /**
     * Ambil nilai setting sebagai float.
     */
    public static function floatValue(string $key, float $default = 0.0): float
    {
        $value = static::value($key);

        return $value !== '' ? (float) $value : $default;
    }
}
