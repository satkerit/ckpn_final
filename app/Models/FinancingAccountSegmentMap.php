<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\FinancingAccountSegmentMapFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FinancingAccountSegmentMap extends Model
{
    /** @use HasFactory<FinancingAccountSegmentMapFactory> */
    use HasFactory;

    protected $table = 'financing_account_segment_map';

    protected $fillable = [
        'financing_account_id',
        'risk_segment_id',
        'effective_from',
        'effective_to',
    ];

    // effective_from & effective_to adalah char(6) format yyyymm — tidak di-cast ke date

    /** Ref: PRD Bab 5 */
    public function financingAccount(): BelongsTo
    {
        return $this->belongsTo(FinancingAccount::class);
    }

    /** Ref: PRD Bab 5 — relasi ke tabel master risk_segments */
    public function riskSegment(): BelongsTo
    {
        return $this->belongsTo(RiskSegment::class);
    }
}
