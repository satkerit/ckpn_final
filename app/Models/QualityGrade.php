<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\QualityGradeFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class QualityGrade extends Model
{
    /** @use HasFactory<QualityGradeFactory> */
    use HasFactory;

    protected $table = 'quality_grades';

    protected $fillable = [
        'code',
        'label',
        'collectibility_number',
        'is_npl',
    ];

    protected function casts(): array
    {
        return [
            'is_npl' => 'boolean',
        ];
    }

    /** Ref: PRD Bab 8 */
    public function outstandingQuarterly(): HasMany
    {
        return $this->hasMany(FinancingOutstandingQuarterly::class);
    }
}
