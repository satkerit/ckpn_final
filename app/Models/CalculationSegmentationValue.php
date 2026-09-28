<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\CalculationSegmentationValueFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Nilai/anggota segmen pada sebuah level segmentasi (mis. kode kantor '001').
 *
 * @property int $id
 * @property int $segmentation_level_id
 * @property string $value
 * @property string|null $label
 * @property bool $is_active
 */
class CalculationSegmentationValue extends Model
{
    /** @use HasFactory<CalculationSegmentationValueFactory> */
    use HasFactory;

    protected $table = 'calculation_segmentation_values';

    protected $fillable = [
        'segmentation_level_id',
        'value',
        'label',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function level(): BelongsTo
    {
        return $this->belongsTo(CalculationSegmentationLevel::class, 'segmentation_level_id');
    }
}
