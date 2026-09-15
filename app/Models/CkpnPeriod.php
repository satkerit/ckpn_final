<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\PdMethod;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Penetapan periode penilaian CKPN.
 * Ref: PRD Bab 12a Step 2, FR-11
 *
 * @property int $id
 * @property string $period yyyymm
 * @property string $status draft|in_progress|completed|approved
 * @property PdMethod|null $pd_method metode PD yang dipilih untuk CKPN Kolektif final
 * @property bool $is_classified apakah klasifikasi sudah dijalankan
 * @property int|null $created_by_user_id
 * @property int|null $approved_by_user_id
 * @property string|null $approved_at
 * @property string|null $notes
 */
class CkpnPeriod extends Model
{
    use HasFactory;

    protected $table = 'ckpn_periods';

    protected $fillable = [
        'period',
        'status',
        'pd_method',
        'is_classified',
        'created_by_user_id',
        'approved_by_user_id',
        'approved_at',
        'notes',
    ];

    protected $casts = [
        'approved_at' => 'datetime',
        'pd_method' => PdMethod::class,
        'is_classified' => 'boolean',
    ];

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by_user_id');
    }

    public function classifications(): HasMany
    {
        return $this->hasMany(CkpnPeriodClassification::class, 'ckpn_period_id');
    }

    public function isDraft(): bool
    {
        return $this->status === 'draft';
    }

    public function isInProgress(): bool
    {
        return $this->status === 'in_progress';
    }

    public function isApproved(): bool
    {
        return $this->status === 'approved';
    }

    /**
     * Semua periode CKPN diurutkan dari terbaru ke terlama.
     * Dipakai oleh Detail Services sebagai opsi input UI.
     *
     * @return string[] format yyyymm, mis. ['202506', '202503']
     */
    public static function orderedPeriods(): array
    {
        return self::orderByDesc('period')->pluck('period')->toArray();
    }
}
