<?php

namespace App\Services;

/**
 * Westport's own internal policy for paying its RMs - "RMs get 10% of what
 * Westport Industrial makes" (2026-10-01, per the boss). Deliberately kept
 * separate from LotPricingService: that class is the Tender No.
 * TNT/KEPDA/011/2026-2027 contract rate Westport charges the government,
 * an entirely different rate set by an entirely different party. This one
 * is Westport's own HR/compensation decision and could change independently
 * of the contract.
 *
 * "What Westport makes" for Lot 1 is the confirmed LsoPayment.amount_minor
 * itself - that field already IS Westport's realized contractual
 * commission (see LsoController::confirmPayment() and
 * docs/lot-pricing-business-rules.md section 5), not the gross sale value
 * and not a value this service recomputes. Since each confirmed payment's
 * amount_minor is already the correct incremental slice of Westport's
 * cumulative commission (LotPricingService::calculateLot1IncrementalCommissionMinor
 * guarantees this - see its own docblock), paying 10% of each payment's
 * amount_minor independently is automatically non-double-counting across
 * multiple payments - no separate cumulative tracking is needed here.
 */
class RmCommissionService
{
    public const RATE_PERCENT = 10;

    public function calculateMinor(int $westportEarnedMinor): int
    {
        if ($westportEarnedMinor <= 0) {
            return 0;
        }

        return (int) round($westportEarnedMinor * self::RATE_PERCENT / 100);
    }
}
