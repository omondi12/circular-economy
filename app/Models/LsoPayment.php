<?php

namespace App\Models;

use App\Services\RmCommissionService;
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
        'gross_amount_minor',
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
            'gross_amount_minor' => 'integer',
            'collected_at' => 'date',
        ];
    }

    /**
     * Only set for a Lot-1 (auction) payment recorded via the gross-sale-
     * value commission calculator (see LsoController::storePayment() and
     * LotPricingService) - the realized sale value amount_minor's
     * commission was computed from. Null for every payment recorded the
     * plain way, including all pre-existing data.
     */
    public function netAmountMinor(): ?int
    {
        return $this->gross_amount_minor === null ? null : $this->gross_amount_minor - $this->amount_minor;
    }

    /**
     * The RM's 10% commission on Westport's earned amount (see
     * RmCommissionService's docblock) - null, never zero, until this
     * payment is confirmed. Westport's own amount_minor isn't authoritative
     * until confirmation either (storePayment()'s figure is provisional),
     * so an RM commission derived from it can't be either.
     */
    public function rmCommissionMinor(): ?int
    {
        if ($this->status !== self::STATUS_CONFIRMED) {
            return null;
        }

        return (new RmCommissionService)->calculateMinor($this->amount_minor);
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
