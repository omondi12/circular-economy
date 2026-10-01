<?php

namespace App\Models;

use App\Services\RmCommissionService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The pricing overlay for one lot/collection line item under a financial
 * LSO - one LsoLot per Collection (see the collections migration's
 * unique collection_id). Reuses Collection's existing lot/category/
 * subcategory/quantity/unit rather than duplicating them; see this
 * migration's docblock for why there's no lso_id column here.
 *
 * rate_minor and expected_revenue_minor are DEFERRED — EXTERNAL PRICING
 * DOCUMENT PENDING: the company's category/method (Auction vs Disposal)
 * pricing lives outside this system and hasn't been provided yet. They
 * stay genuinely null (never 0) until that pricing engine is built, so
 * "not yet priced" is never confused with "priced at zero".
 *
 * status/confirmed_revenue_minor/confirmed_by/confirmed_at (2026-10-01):
 * a Lot 2 collection's expected_revenue_minor is self-reported the moment
 * an RM records it - never verified by Finance, unlike Lot 1's
 * LsoPayment ledger. RM commission requires an authoritative Westport-
 * earned amount, so this mirrors LsoPayment's record->confirm pattern:
 * expected_revenue_minor keeps its exact original meaning (the estimate),
 * confirmed_revenue_minor is the separate, Finance-locked amount actually
 * used for RM commission. Confirming recomputes from the collection's
 * live category/unit/quantity via LotPricingService rather than trusting
 * the stored expected figure - see LsoController::confirmLsoLot().
 */
class LsoLot extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_CONFIRMED = 'confirmed';

    public const STATUS_REJECTED = 'rejected';

    protected $fillable = [
        'collection_id',
        'rate_minor',
        'expected_revenue_minor',
        'status',
        'confirmed_revenue_minor',
        'confirmed_by',
        'confirmed_at',
    ];

    protected function casts(): array
    {
        return [
            'rate_minor' => 'integer',
            'expected_revenue_minor' => 'integer',
            'confirmed_revenue_minor' => 'integer',
            'confirmed_at' => 'datetime',
        ];
    }

    public function collection(): BelongsTo
    {
        return $this->belongsTo(Collection::class);
    }

    public function confirmedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'confirmed_by');
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    /**
     * The RM's 10% commission on Westport's confirmed Lot 2 revenue - null,
     * never zero, until this lot is confirmed. Mirrors
     * LsoPayment::rmCommissionMinor() exactly; see RmCommissionService's
     * docblock for why Lot 1 and Lot 2 share this same 10% policy despite
     * having completely separate pricing mechanisms.
     */
    public function rmCommissionMinor(): ?int
    {
        if ($this->status !== self::STATUS_CONFIRMED || $this->confirmed_revenue_minor === null) {
            return null;
        }

        return (new RmCommissionService)->calculateMinor($this->confirmed_revenue_minor);
    }
}
