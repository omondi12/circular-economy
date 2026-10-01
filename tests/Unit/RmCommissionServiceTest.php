<?php

namespace Tests\Unit;

use App\Services\LotPricingService;
use App\Services\RmCommissionService;
use PHPUnit\Framework\TestCase;

class RmCommissionServiceTest extends TestCase
{
    private RmCommissionService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new RmCommissionService;
    }

    public function test_rm_commission_is_ten_percent_of_what_westport_earned(): void
    {
        $this->assertSame(500_000, $this->service->calculateMinor(5_000_000)); // KES 50,000 -> 5,000
        $this->assertSame(1_000_000, $this->service->calculateMinor(10_000_000)); // KES 100,000 -> 10,000
        $this->assertSame(1_350_000, $this->service->calculateMinor(13_500_000)); // KES 135,000 -> 13,500
        $this->assertSame(10_000_000, $this->service->calculateMinor(100_000_000)); // KES 1,000,000 -> 100,000
    }

    public function test_rm_commission_is_zero_for_zero_or_negative_earnings(): void
    {
        $this->assertSame(0, $this->service->calculateMinor(0));
        $this->assertSame(0, $this->service->calculateMinor(-5_000));
    }

    /**
     * The exact scenario the business clarification gave: a KES 150,000
     * asset sale produces KES 13,500 Westport commission (10% of the first
     * 100,000, 7% of the remaining 50,000) - and the RM commission is 10%
     * of THAT 13,500, not 10% of the KES 150,000 sale value. The wrong
     * answer (KES 15,000) is explicitly asserted against.
     */
    public function test_lot1_worked_example_rm_commission_is_ten_percent_of_westports_commission_not_the_sale_value(): void
    {
        $pricing = new LotPricingService;

        $saleValueMinor = 15_000_000; // KES 150,000
        $westportCommissionMinor = $pricing->calculateLot1CommissionMinor($saleValueMinor);

        $this->assertSame(1_350_000, $westportCommissionMinor); // KES 13,500

        $rmCommissionMinor = $this->service->calculateMinor($westportCommissionMinor);

        $this->assertSame(135_000, $rmCommissionMinor); // KES 1,350
        $this->assertNotSame(1_500_000, $rmCommissionMinor); // NOT 10% of the sale value (KES 15,000)
    }

    /**
     * Gross sale value, Westport's commission, and the RM's commission are
     * three distinct numbers, each computed from a different base - never
     * interchangeable, even though two of them happen to use a 10% rate.
     */
    public function test_gross_sale_value_westport_commission_and_rm_commission_are_three_different_numbers(): void
    {
        $pricing = new LotPricingService;

        $grossSaleValueMinor = 15_000_000; // KES 150,000
        $westportCommissionMinor = $pricing->calculateLot1CommissionMinor($grossSaleValueMinor); // KES 13,500
        $rmCommissionMinor = $this->service->calculateMinor($westportCommissionMinor); // KES 1,350

        $this->assertNotSame($grossSaleValueMinor, $westportCommissionMinor);
        $this->assertNotSame($westportCommissionMinor, $rmCommissionMinor);
        $this->assertNotSame($grossSaleValueMinor, $rmCommissionMinor);
    }

    /**
     * Two confirmed payments of Westport-earned KES 80,000 each (already
     * non-overlapping, incremental slices by construction - see
     * LotPricingService::calculateLot1IncrementalCommissionMinor) must
     * produce RM commission that simply sums per-payment, with no separate
     * cumulative tracking needed and no double counting.
     */
    public function test_rm_commission_on_multiple_payments_sums_without_double_counting(): void
    {
        $payment1 = $this->service->calculateMinor(8_000_000); // Westport earned KES 80,000
        $payment2 = $this->service->calculateMinor(8_000_000); // Westport earned another KES 80,000

        $this->assertSame(800_000, $payment1); // KES 8,000
        $this->assertSame(800_000, $payment2); // KES 8,000
        $this->assertSame(1_600_000, $payment1 + $payment2); // KES 16,000 total
    }
}
