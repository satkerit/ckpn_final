<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\SegmentType;
use Database\Factories\CalculationSegmentationLevelFactory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Level segmentasi bertingkat (mis. 1 = Kode Kantor, 2 = Jenis Penggunaan, 3 = Akad).
 * Urutan level ditentukan level_order.
 *
 * Ref: PRD Bab 5 + Permintaan User (segmentasi bertingkat).
 *
 * @property int $id
 * @property int $level_order
 * @property SegmentType $segment_type
 * @property string $label
 * @property bool $is_active
 * @property bool $allow_global_fallback
 * @property string|null $notes
 */
class CalculationSegmentationLevel extends Model
{
    /** @use HasFactory<CalculationSegmentationLevelFactory> */
    use HasFactory;

    protected $table = 'calculation_segmentation_levels';

    protected $fillable = [
        'level_order',
        'segment_type',
        'label',
        'is_active',
        'allow_global_fallback',
        'notes',
    ];

    protected $casts = [
        'segment_type' => SegmentType::class,
        'is_active' => 'boolean',
        'allow_global_fallback' => 'boolean',
    ];

    public function values(): HasMany
    {
        return $this->hasMany(CalculationSegmentationValue::class, 'segmentation_level_id');
    }

    /**
     * Level aktif terurut dari terluar ke terdalam.
     *
     * @return Collection<int, self>
     */
    public static function activeOrdered(): Collection
    {
        return static::query()
            ->with('values')
            ->where('is_active', true)
            ->orderBy('level_order')
            ->get();
    }

    public function segmentTypeLabel(): string
    {
        return $this->segment_type instanceof SegmentType
            ? $this->segment_type->label()
            : (string) $this->segment_type;
    }
}
