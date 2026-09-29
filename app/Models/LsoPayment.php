<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One actual money-collection event against an LSO - the source of truth
 * for real collected money. An LSO's original_amount_minor is never
 * treated as collected; only rows here with status=confirmed count
 * toward outstanding balances, RM target achievement, and any future
 * commission calculation (see Lso::confirmedCollectedMinor()).
 */
class LsoPayment extends Model
{
    public const STATUS_RECORDED = 'recorded';

    public const STATUS_CONFIRMED = 'confirmed';

    public const STATUS_REJECTED = 'rejected';

    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'lso_id',
        'amount_minor',
        'collected_at',
        'status',
        'recorded_by',
        'confirmed_by',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'amount_minor' => 'integer',
            'collected_at' => 'date',
        ];
    }

    public function lso(): BelongsTo
    {
        return $this->belongsTo(Lso::class);
    }

    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    public function confirmedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'confirmed_by');
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_RECORDED;
    }
}
