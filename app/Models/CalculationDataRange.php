<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\CalculationMethodKey;
use App\Enums\UsageType;
use Database\Factories\CalculationDataRangeFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Rentang data PD & LGD (rolling window, forward projection, lookback, matrix count)
 * dengan dukungan segmentasi bertingkat (kode kantor, jenis penggunaan, akad).
 *
 * Ref: PRD Bab 7, 8, 9, 10.
 *
 * @property int $id
 * @property CalculationMethodKey $method
 * @property string $range_key
 * @property string $range_value
 * @property string $range_unit
 * @property string|null $office_code
 * @property int|null $usage_type
 * @property string|null $akad_code
 * @property bool $is_active
 * @property string|null $notes
 * @property string|null $justification_notes
 * @property string|null $recommendation_values
 */
class CalculationDataRange extends Model
{
    /** @use HasFactory<CalculationDataRangeFactory> */
    use HasFactory;

    protected $table = 'calculation_data_ranges';

    protected $fillable = [
        'method',
        'range_key',
        'range_value',
        'range_unit',
        'office_code',
        'usage_type',
        'akad_code',
        'is_active',
        'notes',
        'justification_notes',
        'recommendation_values',
    ];

    protected $casts = [
        'method' => CalculationMethodKey::class,
        'usage_type' => UsageType::class,
        'is_active' => 'boolean',
    ];

    /**
     * Master daftar range_key per metode beserta label, unit, & rekomendasi —
     * dipakai UI untuk menampilkan pilihan parameter yang valid + guidance.
     * Ref: IMPROVEMENT_PLAN_PD_CALCULATION.md Phase 1, Catatan PD.md
     *
     * @return array<string, array{label: string, unit: string, recommended_values?: array<int|string>, guidance?: string}>
     */
    public static function rangeCatalog(): array
    {
        return [
            'pd_netflow_rolling_window_months' => [
                'label' => 'Rolling Window Observasi PD Netflow',
                'unit' => 'months',
                'recommended_values' => [12, 24, 36, 48, 60],
                'guidance' => 'Minimal 12 bulan per Catatan PD; optimal 24–60 sesuai data availability. Lebih panjang = lebih stabil tapi lag trend. Pilihan harus dijustifikasi.',
            ],
            'pd_netflow_forward_projection_months' => [
                'label' => 'Proyeksi Ke Depan PD Netflow',
                'unit' => 'months',
            ],
            'pd_netflow_projection_lookback_months' => [
                'label' => 'Lookback Proyeksi PD Netflow',
                'unit' => 'months',
            ],
            'pd_netflow_projection_method' => [
                'label' => 'Metode Proyeksi PD Netflow',
                'unit' => 'text',
            ],
            'pd_migration_matrix_count' => [
                'label' => 'Jumlah Matriks Migrasi PD',
                'unit' => 'count',
                'guidance' => 'Dinamis: dihitung dari pd_migration_lookback_months / 3. Legacy default 4 (12 bulan). Jika explicit set, gunakan itu (backward compat).',
            ],
            'pd_migration_lookback_months' => [
                'label' => 'Lookback Migrasi PD',
                'unit' => 'months',
                'recommended_values' => [12, 24, 36, 48, 60],
                'guidance' => 'Rentang historis migrasi untuk pengamatan trend. Matrix count = ceil(lookback / 3). Harus selaras dengan pd_netflow_rolling_window_months untuk konsistensi.',
            ],
            'lgd_er_rolling_window_years' => [
                'label' => 'Rolling Window LGD Expected Recoveries',
                'unit' => 'years',
            ],
            'lgd_er_use_all_account' => [
                'label' => 'LGD ER Paksa Semua Akun',
                'unit' => 'flag',
            ],
            'lgd_cs_selling_cost_rate' => [
                'label' => 'Biaya Penjualan Jaminan LGD CS',
                'unit' => 'rate',
            ],
            'ckpn_individual_selling_cost_rate' => [
                'label' => 'Biaya Penjualan Jaminan CKPN Individual',
                'unit' => 'rate',
            ],
            'ckpn_collective_pd_method' => [
                'label' => 'Metode PD CKPN Kolektif (netflow|migration)',
                'unit' => 'text',
            ],
        ];
    }

    /**
     * Ambil nilai rentang untuk method + range_key tertentu, dengan prioritas
     * segmen paling spesifik: (office+usage+akad) > (usage+akad) > (usage) > global.
     * Kembalikan $default jika tidak ada baris aktif.
     */
    public static function resolveValue(
        CalculationMethodKey $method,
        string $rangeKey,
        ?string $officeCode = null,
        ?int $usageType = null,
        ?string $akadCode = null,
        mixed $default = null
    ): mixed {
        $query = static::query()
            ->where('method', $method->value)
            ->where('range_key', $rangeKey)
            ->where('is_active', true);

        $rows = $query->get();

        if ($rows->isEmpty()) {
            return $default;
        }

        // Prioritas: semakin banyak kriteria segmen yang cocok, semakin tinggi skornya.
        $best = null;
        $bestScore = -1;

        foreach ($rows as $row) {
            // Normalisasi usage_type: kolom di-cast ke enum UsageType, sementara argumen $usageType int.
            // Tanpa normalisasi ini, prioritas segmen spesifik TIDAK PERNAH cocok (enum !== int)
            // sehingga parameter per-segmen diabaikan dan selalu jatuh ke baris global.
            $rowUsageType = $row->usage_type instanceof UsageType ? $row->usage_type->value : $row->usage_type;

            if ($row->office_code !== null && $row->office_code !== $officeCode) {
                continue;
            }
            if ($rowUsageType !== null && (int) $rowUsageType !== $usageType) {
                continue;
            }
            if ($row->akad_code !== null && $row->akad_code !== $akadCode) {
                continue;
            }

            $score = ($row->office_code !== null ? 4 : 0)
                + ($row->usage_type !== null ? 2 : 0)
                + ($row->akad_code !== null ? 1 : 0);

            if ($score > $bestScore) {
                $bestScore = $score;
                $best = $row;
            }
        }

        return $best?->range_value ?? $default;
    }

    public function methodLabel(): string
    {
        return $this->method instanceof CalculationMethodKey ? $this->method->label() : (string) $this->method;
    }

    public function usageTypeLabel(): string
    {
        return $this->usage_type?->label() ?? 'Semua';
    }

    /** Deskripsi segmen untuk tampilan tabel. */
    public function segmentLabel(): string
    {
        $parts = [];
        if ($this->office_code !== null) {
            $parts[] = 'Kantor: '.$this->office_code;
        }
        if ($this->usage_type !== null) {
            $parts[] = 'Penggunaan: '.$this->usage_type->label();
        }
        if ($this->akad_code !== null) {
            $parts[] = 'Akad: '.$this->akad_code;
        }

        return $parts === [] ? 'Global (semua segmen)' : implode(' | ', $parts);
    }
}
