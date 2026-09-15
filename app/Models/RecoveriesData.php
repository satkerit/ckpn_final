<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\RecoveriesDataFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RecoveriesData extends Model
{
    /** @use HasFactory<RecoveriesDataFactory> */
    use HasFactory;

    protected $table = 'recoveries_data';

    protected $fillable = [
        'financing_account_id',
        'recovery_date',
        'recovery_amount',
        'period',
    ];

    protected function casts(): array
    {
        return [
            'recovery_date' => 'string',
            'recovery_amount' => 'decimal:2',
        ];
    }

    /** Ref: PRD Bab 9 */
    public function financingAccount(): BelongsTo
    {
        return $this->belongsTo(FinancingAccount::class);
    }
}
