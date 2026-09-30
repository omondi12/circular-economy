<?php

namespace App\Services;

/**
 * Authoritative commercial pricing from Tender No. TNT/KEPDA/011/2026-2027
 * (Kenya Procurement and Disposal Agency award letter to Westport
 * Industrial Limited, 17 Aug 2026). These rates come directly from that
 * contract - they are not invented, and must not be changed without a
 * new/amended award letter.
 *
 * Lot 1 (Sale by Public Auction): a TIERED COMMISSION on the realized
 * sale value - 10% of the first KES 100,000, 7% of anything above. This
 * is not a per-unit rate and has no relationship to item quantity.
 *
 * Lot 2 (Waste Disposal Management): a flat per-unit KES rate, specific
 * to 5 contractual pricing buckets. WasteCategories' actual Lot-2
 * taxonomy has more categories than the contract defines - see
 * CATEGORY_MAP below, which is this service's own documented ASSUMPTION
 * for reconciling the two, not contract text. See
 * docs/lot-pricing-business-rules.md for the full breakdown of what's
 * contractual fact versus implementation decision.
 */
class LotPricingService
{
    /**
     * ASSUMPTION (ticket #1, not contract text): the KES 100,000 tier
     * threshold is evaluated per LSO's own cumulative confirmed sale
     * value, not per individual lot/item and not cumulative across the
     * wider framework period. Chosen because it needs no state beyond
     * what a single Lso's payments already carry - the least risky
     * interpretation available. Revisit if the business confirms a
     * different scope.
     */
    public const LOT1_TIER_THRESHOLD_MINOR = 10_000_000; // KES 100,000

    public const LOT1_TIER_1_RATE_PERCENT = 10;

    public const LOT1_TIER_2_RATE_PERCENT = 7;

    public const BUCKET_MEDICAL_AND_INDUSTRIAL = 'medical_and_industrial_waste';

    public const BUCKET_LIQUID = 'liquid_waste';

    public const BUCKET_EWASTE = 'e_waste';

    public const BUCKET_SOLID = 'solid_waste';

    public const BUCKET_ANY_OTHER = 'any_other_waste';

    /** Contract-defined per-unit rates (Lot 2), in minor units (KES x 100). */
    private const LOT2_RATES_MINOR = [
        self::BUCKET_MEDICAL_AND_INDUSTRIAL => 20_000,  // KES 200 / Kg
        self::BUCKET_LIQUID => 2_000,                    // KES 20 / Ltr
        self::BUCKET_EWASTE => 12_000,                   // KES 120 / Kg
        self::BUCKET_SOLID => 650_000,                   // KES 6,500 / Ton
        self::BUCKET_ANY_OTHER => 25_000,                // KES 250 / Kg
    ];

    /**
     * The contractual billing unit each bucket must be recorded in. No
     * conversion is performed (ticket #3) - a Collection recorded in any
     * other unit is treated as unpriceable rather than guessed at, since
     * a conversion factor (density, etc.) is a business input this
     * service has no authority to invent.
     */
    private const LOT2_REQUIRED_UNIT = [
        self::BUCKET_MEDICAL_AND_INDUSTRIAL => 'kg',
        self::BUCKET_LIQUID => 'litres',
        self::BUCKET_EWASTE => 'kg',
        self::BUCKET_SOLID => 'tonnes',
        self::BUCKET_ANY_OTHER => 'kg',
    ];

    /**
     * ASSUMPTION (ticket #2, not contract text): how WasteCategories'
     * seven Lot-2 categories map onto the contract's five pricing
     * buckets.
     *
     * - `medical_waste` and `industrial_hazardous` both map to the
     *   contract's single "Medical and Industrial waste" bucket - the
     *   contract itself names these together as one rate.
     * - `other_waste` maps to "Any Other Waste" as the direct semantic
     *   match.
     * - `construction_waste` has NO contractual rate and is deliberately
     *   left unmapped (null) rather than guessed - its application units
     *   (tonnes/m3) don't even match the "Any Other Waste" bucket's Kg
     *   basis, so folding it in would silently misprice it.
     */
    private const CATEGORY_MAP = [
        'medical_waste' => self::BUCKET_MEDICAL_AND_INDUSTRIAL,
        'industrial_hazardous' => self::BUCKET_MEDICAL_AND_INDUSTRIAL,
        'liquid_waste' => self::BUCKET_LIQUID,
        'ewaste' => self::BUCKET_EWASTE,
        'solid_waste' => self::BUCKET_SOLID,
        'other_waste' => self::BUCKET_ANY_OTHER,
        'construction_waste' => null,
    ];

    public function contractBucketFor(string $category): ?string
    {
        return self::CATEGORY_MAP[$category] ?? null;
    }

    public function requiredUnitFor(string $bucket): ?string
    {
        return self::LOT2_REQUIRED_UNIT[$bucket] ?? null;
    }

    public function rateMinorFor(string $bucket): ?int
    {
        return self::LOT2_RATES_MINOR[$bucket] ?? null;
    }

    /**
     * Lot 2 per-unit charge: quantity x contractual rate, in minor units.
     * Returns null - never a guessed number - when the category has no
     * contractual mapping (e.g. construction_waste) or the recorded unit
     * doesn't match that bucket's contractual billing unit. Negative
     * quantity is also treated as invalid (null); zero is valid (charges
     * KES 0, not an error).
     */
    public function calculateLot2ChargeMinor(string $category, string $unit, float $quantity): ?int
    {
        if ($quantity < 0) {
            return null;
        }

        $bucket = $this->contractBucketFor($category);
        if ($bucket === null) {
            return null;
        }

        if ($unit !== $this->requiredUnitFor($bucket)) {
            return null;
        }

        return (int) round($quantity * $this->rateMinorFor($bucket));
    }

    /**
     * Lot 1 tiered auction commission on a single cumulative sale value:
     * 10% of the first KES 100,000, 7% of anything above (contract-
     * mandated rates; see LOT1_TIER_THRESHOLD_MINOR's docblock for the
     * one assumption involved - the scope the threshold applies over).
     * Zero or negative sale value yields zero commission rather than an
     * error, since "no sale" trivially has no commission.
     *
     * This is NOT what a multi-payment LSO should call directly per
     * payment - see calculateLot1IncrementalCommissionMinor().
     */
    public function calculateLot1CommissionMinor(int $saleValueMinor): int
    {
        if ($saleValueMinor <= 0) {
            return 0;
        }

        if ($saleValueMinor <= self::LOT1_TIER_THRESHOLD_MINOR) {
            return (int) round($saleValueMinor * self::LOT1_TIER_1_RATE_PERCENT / 100);
        }

        $tier1 = (int) round(self::LOT1_TIER_THRESHOLD_MINOR * self::LOT1_TIER_1_RATE_PERCENT / 100);
        $tier2 = (int) round(($saleValueMinor - self::LOT1_TIER_THRESHOLD_MINOR) * self::LOT1_TIER_2_RATE_PERCENT / 100);

        return $tier1 + $tier2;
    }

    /**
     * The correct way to price ONE payment when an LSO can have several -
     * the marginal commission for $thisPaymentGrossMinor, given
     * $priorCumulativeGrossMinor already confirmed under this same LSO.
     * The KES 100,000 tier is a property of the LSO's whole cumulative
     * sale value, not of any one payment in isolation: pricing each
     * payment independently (calculateLot1CommissionMinor() called
     * separately per payment) would let every payment re-claim the 10%
     * tier from scratch - e.g. two KES 80,000 payments would wrongly
     * total KES 16,000 (10% x 2) instead of the correct KES 14,200
     * (10% of the first 100,000 of the true KES 160,000 total, 7% of the
     * remaining 60,000). Computed as the difference between the
     * cumulative commission before and after this payment, so it's
     * exact regardless of how many payments came before it.
     */
    public function calculateLot1IncrementalCommissionMinor(int $priorCumulativeGrossMinor, int $thisPaymentGrossMinor): int
    {
        $before = $this->calculateLot1CommissionMinor(max(0, $priorCumulativeGrossMinor));
        $after = $this->calculateLot1CommissionMinor(max(0, $priorCumulativeGrossMinor) + $thisPaymentGrossMinor);

        return $after - $before;
    }
}
