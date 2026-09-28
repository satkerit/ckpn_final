<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ParameterMethod;
use App\Enums\UsageType;
use Database\Factories\CalculationColumnConfigFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Penentuan kolom tabel yang dipakai untuk EAD / dasar perhitungan PD / dasar CKPN
 * berdasarkan POKPBY (akad_code).
 *
 * Ref: PRD Bab 15 + Permintaan User.
 *
 * @property int $id
 * @property ParameterMethod $method
 * @property string $pokpby_code
 * @property string|null $pokpby_label
 * @property string $source_table
 * @property string $column_name
 * @property bool $require_maturity
 * @property bool $is_active
 * @property string|null $notes
 */
class CalculationColumnConfig extends Model
{
    /** @use HasFactory<CalculationColumnConfigFactory> */
    use HasFactory;

    protected $table = 'calculation_column_configs';

    protected $fillable = [
        'method',
        'pokpby_code',
        'pokpby_label',
        'source_table',
        'column_name',
        'require_maturity',
        'is_active',
        'notes',
    ];

    protected $casts = [
        'method' => ParameterMethod::class,
        'require_maturity' => 'boolean',
        'is_active' => 'boolean',
    ];

    /**
     * Kolom tersedia per tabel sumber — dipakai untuk pilihan dropdown di UI.
     *
     * @return array<string, array<int, string>>
     */
    public static function availableColumns(): array
    {
        return [
            'financing_account_periods' => [
                'outstanding_balance',
                'tgkmdl',
                'ppka',
                'collectibility',
                'tgkhari',
            ],
            'financing_accounts' => [
                'akad_code',
                'product_code',
                'office_code',
                'usage_type',
                'economic_sector',
            ],
        ];
    }

    /**
     * Nama kolom yang digunakan untuk POKPBY tertentu pada method tertentu.
     * Kembalikan $default jika tidak ada konfigurasi aktif.
     */
    public static function columnFor(ParameterMethod $method, string $pokpbyCode, string $default = 'outstanding_balance'): string
    {
        $column = static::query()
            ->where('method', $method->value)
            ->where('pokpby_code', $pokpbyCode)
            ->where('is_active', true)
            ->value('column_name');

        return $column !== null && trim((string) $column) !== '' ? (string) $column : $default;
    }

    /**
     * Apakah POKPBY tertentu diwajibkan sudah jatuh tempo.
     */
    public static function requiresMaturity(string $pokpbyCode): bool
    {
        return (bool) static::query()
            ->where('pokpby_code', $pokpbyCode)
            ->where('is_active', true)
            ->where('require_maturity', true)
            ->exists();
    }

    public function methodLabel(): string
    {
        return $this->method instanceof ParameterMethod ? $this->method->label() : (string) $this->method;
    }

    public function usageTypeLabel(): ?string
    {
        return UsageType::tryFrom((int) $this->pokpby_code)?->label();
    }
}
