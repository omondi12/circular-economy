<?php

namespace Tests\Unit;

use App\Services\LotPricingService;
use PHPUnit\Framework\TestCase;

class LotPricingServiceTest extends TestCase
{
    private LotPricingService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new LotPricingService;
    }

    // --- Lot 1: tiered auction commission ------------------------------

    /**
     * The exact four examples given in the contract-derived spec:
     * 50,000 -> 5,000; 100,000 -> 10,000; 150,000 -> 13,500; 1,000,000 -> 73,000.
     */
    public function test_lot1_commission_matches_the_required_worked_examples(): void
    {
        $this->assertSame(500_000, $this->service->calculateLot1CommissionMinor(5_000_000)); // KES 50,000 -> 5,000
        $this->assertSame(1_000_000, $this->service->calculateLot1CommissionMinor(10_000_000)); // KES 100,000 -> 10,000
        $this->assertSame(1_350_000, $this->service->calculateLot1CommissionMinor(15_000_000)); // KES 150,000 -> 13,500
        $this->assertSame(7_300_000, $this->service->calculateLot1CommissionMinor(100_000_000)); // KES 1,000,000 -> 73,000
    }

    public function test_lot1_commission_is_zero_for_zero_or_negative_sale_value(): void
    {
        $this->assertSame(0, $this->service->calculateLot1CommissionMinor(0));
        $this->assertSame(0, $this->service->calculateLot1CommissionMinor(-1000));
    }

    public function test_lot1_commission_never_applies_the_full_rate_to_the_whole_amount(): void
    {
        // A naive flat-10% calculation would give 15,000; the correct
        // tiered answer is 13,500 - this pins the tiering itself, not
        // just the headline example already covered above.
        $naiveFlatTen = (int) round(15_000_000 * 0.10);
        $actual = $this->service->calculateLot1CommissionMinor(15_000_000);

        $this->assertNotSame($naiveFlatTen, $actual);
        $this->assertSame(1_350_000, $actual);
    }

    public function test_lot1_commission_boundary_values(): void
    {
        // Just below the threshold - pure 10% tier. round(999,999.9) rounds
        // up to the same KES 10,000.00 as the threshold itself.
        $this->assertSame(1_000_000, $this->service->calculateLot1CommissionMinor(9_999_999)); // KES 99,999.99 -> KES 10,000.00

        // Exactly at the threshold.
        $this->assertSame(1_000_000, $this->service->calculateLot1CommissionMinor(10_000_000));

        // One cent above the threshold - the extra KES 0.01 rounds away
        // (round(0.07) = 0), a deterministic consequence of minor-unit
        // rounding, not an error.
        $this->assertSame(1_000_000, $this->service->calculateLot1CommissionMinor(10_000_001));

        // KES 100,001 above threshold by a whole shilling.
        $this->assertSame(1_000_007, $this->service->calculateLot1CommissionMinor(10_000_100));
    }

    public function test_lot1_commission_on_a_large_sale_value(): void
    {
        // KES 100,000,000 sale value.
        $this->assertSame(700_300_000, $this->service->calculateLot1CommissionMinor(10_000_000_000));
    }

    // --- Lot 1: incremental commission across multiple payments --------

    /**
     * Two KES 80,000 payments against one LSO must total the correct
     * cumulative commission on KES 160,000 (14,200), never 2x the
     * independent 10%-each result (16,000) - the exact scenario a naive
     * per-payment calculation gets wrong.
     */
    public function test_lot1_incremental_commission_never_double_dips_the_tier_across_multiple_payments(): void
    {
        $payment1 = $this->service->calculateLot1IncrementalCommissionMinor(0, 8_000_000);
        $payment2 = $this->service->calculateLot1IncrementalCommissionMinor(8_000_000, 8_000_000);

        $this->assertSame(800_000, $payment1); // 10% of 80,000
        $this->assertSame(620_000, $payment2); // remaining 20,000 at 10% + 60,000 at 7%

        $total = $payment1 + $payment2;
        $this->assertSame(1_420_000, $total); // KES 14,200
        $this->assertSame($this->service->calculateLot1CommissionMinor(16_000_000), $total);
        $this->assertNotSame(1_600_000, $total); // the wrong, naive 10%-twice answer
    }

    /**
     * KES 100,000 then KES 50,000: the second payment must be charged
     * entirely at 7% (it's all above the threshold once the first
     * payment already used it up), not another partial 10% slice.
     */
    public function test_lot1_incremental_commission_applies_the_second_tier_once_the_threshold_is_already_used(): void
    {
        $payment1 = $this->service->calculateLot1IncrementalCommissionMinor(0, 10_000_000);
        $payment2 = $this->service->calculateLot1IncrementalCommissionMinor(10_000_000, 5_000_000);

        $this->assertSame(1_000_000, $payment1); // 10% of 100,000
        $this->assertSame(350_000, $payment2); // 7% of 50,000 - fully above the threshold

        $this->assertSame(1_350_000, $payment1 + $payment2); // KES 13,500, matches the single-payment KES 150,000 example
    }

    public function test_lot1_incremental_commission_is_order_independent_in_total(): void
    {
        // 50,000 then 100,000 should total the same as 100,000 then 50,000.
        $a = $this->service->calculateLot1IncrementalCommissionMinor(0, 5_000_000)
            + $this->service->calculateLot1IncrementalCommissionMinor(5_000_000, 10_000_000);
        $b = $this->service->calculateLot1IncrementalCommissionMinor(0, 10_000_000)
            + $this->service->calculateLot1IncrementalCommissionMinor(10_000_000, 5_000_000);

        $this->assertSame($a, $b);
        $this->assertSame(1_350_000, $a);
    }

    // --- Lot 2: per-unit waste disposal rates --------------------------

    public function test_lot2_charge_for_each_contractual_category_at_its_correct_unit(): void
    {
        $this->assertSame(2_000_000, $this->service->calculateLot2ChargeMinor('medical_waste', 'kg', 100)); // 100kg x KES 200
        $this->assertSame(2_000_000, $this->service->calculateLot2ChargeMinor('industrial_hazardous', 'kg', 100)); // shares the same bucket/rate
        $this->assertSame(200_000, $this->service->calculateLot2ChargeMinor('liquid_waste', 'litres', 100)); // 100 litres x KES 20
        $this->assertSame(1_200_000, $this->service->calculateLot2ChargeMinor('ewaste', 'kg', 100)); // 100kg x KES 120
        $this->assertSame(65_000_000, $this->service->calculateLot2ChargeMinor('solid_waste', 'tonnes', 100)); // 100 tonnes x KES 6,500
        $this->assertSame(2_500_000, $this->service->calculateLot2ChargeMinor('other_waste', 'kg', 100)); // 100kg x KES 250
    }

    public function test_lot2_charge_rejects_an_incompatible_unit_rather_than_guessing(): void
    {
        $this->assertNull($this->service->calculateLot2ChargeMinor('liquid_waste', 'm3', 100));
        $this->assertNull($this->service->calculateLot2ChargeMinor('ewaste', 'tonnes', 100));
        $this->assertNull($this->service->calculateLot2ChargeMinor('solid_waste', 'kg', 100));
    }

    public function test_lot2_charge_is_null_for_an_unmapped_category(): void
    {
        $this->assertNull($this->service->calculateLot2ChargeMinor('construction_waste', 'tonnes', 100));
        $this->assertNull($this->service->calculateLot2ChargeMinor('construction_waste', 'kg', 100));
    }

    public function test_lot2_charge_for_zero_quantity_is_zero_not_an_error(): void
    {
        $this->assertSame(0, $this->service->calculateLot2ChargeMinor('medical_waste', 'kg', 0));
    }

    public function test_lot2_charge_rejects_negative_quantity(): void
    {
        $this->assertNull($this->service->calculateLot2ChargeMinor('medical_waste', 'kg', -5));
    }

    public function test_lot2_charge_handles_decimal_quantity(): void
    {
        $this->assertSame(2_500, $this->service->calculateLot2ChargeMinor('medical_waste', 'kg', 0.125)); // 0.125kg x KES 200 = KES 25.00
    }

    public function test_lot2_charge_on_a_large_quantity(): void
    {
        $this->assertSame(650_000_000, $this->service->calculateLot2ChargeMinor('solid_waste', 'tonnes', 1000)); // 1,000 tonnes x KES 6,500
    }

    public function test_lot2_bucket_and_unit_lookups(): void
    {
        $this->assertSame(LotPricingService::BUCKET_MEDICAL_AND_INDUSTRIAL, $this->service->contractBucketFor('medical_waste'));
        $this->assertSame(LotPricingService::BUCKET_MEDICAL_AND_INDUSTRIAL, $this->service->contractBucketFor('industrial_hazardous'));
        $this->assertNull($this->service->contractBucketFor('construction_waste'));
        $this->assertNull($this->service->contractBucketFor('not_a_real_category'));

        $this->assertSame('kg', $this->service->requiredUnitFor(LotPricingService::BUCKET_MEDICAL_AND_INDUSTRIAL));
        $this->assertSame('litres', $this->service->requiredUnitFor(LotPricingService::BUCKET_LIQUID));
        $this->assertSame('tonnes', $this->service->requiredUnitFor(LotPricingService::BUCKET_SOLID));
    }
}
