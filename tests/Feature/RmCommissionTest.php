<?php

namespace Tests\Feature;

use App\Models\Lso;
use App\Models\LsoPayment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * "RMs get a 10% commission of what Westport Industrial makes"
 * (2026-10-01, per the boss) - a separate concept from Westport's own
 * contractual commission (LotPricingService), computed on top of it. See
 * RmCommissionService's docblock for why these are kept apart.
 */
class RmCommissionTest extends TestCase
{
    use RefreshDatabase;

    private function makeRm(string $phone = '254700000301'): User
    {
        return User::factory()->create(['role' => User::ROLE_RM, 'phone_number' => $phone, 'is_active' => true]);
    }

    private function makeAdmin(string $phone = '254700000401'): User
    {
        return User::factory()->create(['role' => User::ROLE_ADMIN, 'phone_number' => $phone]);
    }

    private function createLso(User $rm, int $originalAmountMinor = 999_999_999_999): Lso
    {
        return Lso::create([
            'reference_number' => 'LSO-'.uniqid(),
            'user_id' => $rm->id,
            'customer_name' => 'Acme Transport Ltd',
            'original_amount_minor' => $originalAmountMinor,
            'issue_date' => now(),
            'document_path' => 'lso-documents/fake.pdf',
            'document_original_filename' => 'fake.pdf',
            'document_mime' => 'application/pdf',
            'created_by' => $rm->id,
        ]);
    }

    public function test_rm_commission_is_null_until_the_payment_is_confirmed(): void
    {
        $rm = $this->makeRm();
        $lso = $this->createLso($rm);

        $payment = LsoPayment::create([
            'lso_id' => $lso->id, 'amount_minor' => 1_000_000, 'collected_at' => now(),
            'status' => LsoPayment::STATUS_RECORDED, 'recorded_by' => $rm->id,
        ]);

        $this->assertNull($payment->rmCommissionMinor());
    }

    public function test_rm_commission_is_ten_percent_of_westports_confirmed_commission(): void
    {
        $rm = $this->makeRm();
        $admin = $this->makeAdmin();
        $lso = $this->createLso($rm);

        // KES 150,000 gross sale -> Westport commission KES 13,500 (the
        // existing Lot 1 tiered contract rule, unchanged).
        $this->actingAs($rm)->post(route('rm.lsos.payments.store', $lso), [
            'gross_amount' => 150000, 'collected_at' => now()->toDateString(),
        ])->assertRedirect();
        $payment = LsoPayment::first();

        $this->actingAs($admin)->post(route('admin.lso-payments.confirm', $payment))->assertRedirect();
        $payment->refresh();

        $this->assertSame(1_350_000, $payment->amount_minor); // Westport's commission, KES 13,500
        $this->assertSame(135_000, $payment->rmCommissionMinor()); // RM commission, KES 1,350
        $this->assertNotSame(1_500_000, $payment->rmCommissionMinor()); // NOT 10% of the KES 150,000 sale value
    }

    public function test_rejected_payments_never_produce_an_rm_commission(): void
    {
        $rm = $this->makeRm();
        $admin = $this->makeAdmin();
        $lso = $this->createLso($rm);

        $payment = LsoPayment::create([
            'lso_id' => $lso->id, 'amount_minor' => 1_000_000, 'collected_at' => now(),
            'status' => LsoPayment::STATUS_RECORDED, 'recorded_by' => $rm->id,
        ]);

        $this->actingAs($admin)->post(route('admin.lso-payments.reject', $payment))->assertRedirect();

        $this->assertNull($payment->fresh()->rmCommissionMinor());
    }

    /**
     * Critical regression: two KES 80,000 gross payments against one LSO
     * produce Westport commission of KES 8,000 then KES 6,200 (the
     * existing incremental-tiering fix, unchanged) - RM commission must
     * independently sum to 10% of each, KES 800 + KES 620 = KES 1,420,
     * never a naive 10% of the combined KES 160,000 gross sale value.
     */
    public function test_rm_commission_on_multiple_confirmed_payments_never_double_counts(): void
    {
        $rm = $this->makeRm();
        $admin = $this->makeAdmin();
        $lso = $this->createLso($rm);

        $this->actingAs($rm)->post(route('rm.lsos.payments.store', $lso), [
            'gross_amount' => 80000, 'collected_at' => now()->toDateString(),
        ])->assertRedirect();
        $this->actingAs($rm)->post(route('rm.lsos.payments.store', $lso), [
            'gross_amount' => 80000, 'collected_at' => now()->toDateString(),
        ])->assertRedirect();

        [$payment1, $payment2] = LsoPayment::orderBy('id')->get();
        $this->actingAs($admin)->post(route('admin.lso-payments.confirm', $payment1))->assertRedirect();
        $this->actingAs($admin)->post(route('admin.lso-payments.confirm', $payment2))->assertRedirect();

        $payment1->refresh();
        $payment2->refresh();

        $this->assertSame(800_000, $payment1->amount_minor); // Westport KES 8,000
        $this->assertSame(620_000, $payment2->amount_minor); // Westport KES 6,200

        $this->assertSame(80_000, $payment1->rmCommissionMinor()); // RM KES 800
        $this->assertSame(62_000, $payment2->rmCommissionMinor()); // RM KES 620

        $totalRmCommission = $payment1->rmCommissionMinor() + $payment2->rmCommissionMinor();
        $this->assertSame(142_000, $totalRmCommission); // KES 1,420
        $this->assertNotSame(1_600_000, $totalRmCommission); // NOT 10% of the combined KES 160,000 gross sale
    }

    public function test_rm_dashboard_shows_only_the_logged_in_rms_own_commission(): void
    {
        $rmA = $this->makeRm('254700000302');
        $rmB = $this->makeRm('254700000303');
        $admin = $this->makeAdmin();

        $lsoA = $this->createLso($rmA);
        $lsoB = $this->createLso($rmB);

        $this->actingAs($rmA)->post(route('rm.lsos.payments.store', $lsoA), [
            'gross_amount' => 100000, 'collected_at' => now()->toDateString(),
        ])->assertRedirect();
        $this->actingAs($rmB)->post(route('rm.lsos.payments.store', $lsoB), [
            'gross_amount' => 200000, 'collected_at' => now()->toDateString(),
        ])->assertRedirect();

        [$paymentA, $paymentB] = LsoPayment::orderBy('id')->get();
        $this->actingAs($admin)->post(route('admin.lso-payments.confirm', $paymentA))->assertRedirect();
        $this->actingAs($admin)->post(route('admin.lso-payments.confirm', $paymentB))->assertRedirect();

        // RM A's Westport commission is KES 10,000 -> RM commission KES 1,000.
        // RM B's is a separate LSO entirely and must never leak into A's total.
        $response = $this->actingAs($rmA)->get(route('rm.dashboard'));
        $response->assertOk();
        $response->assertSee('KES 1,000');
        $response->assertDontSee('KES 2,000'); // RM B's commission figure
    }

    public function test_rm_performance_page_aggregates_commission_correctly_and_is_finance_only(): void
    {
        $rm = $this->makeRm();
        $admin = $this->makeAdmin();
        $lso = $this->createLso($rm);

        $this->actingAs($rm)->post(route('rm.lsos.payments.store', $lso), [
            'gross_amount' => 150000, 'collected_at' => now()->toDateString(),
        ])->assertRedirect();
        $payment = LsoPayment::first();
        $this->actingAs($admin)->post(route('admin.lso-payments.confirm', $payment))->assertRedirect();

        // Westport commission KES 13,500 -> RM commission KES 1,350.
        $this->actingAs($admin)->get(route('admin.rm-performance'))
            ->assertOk()
            ->assertSee('KES 1,350');

        // An RM has no access to the Finance-facing aggregate view at all.
        $this->actingAs($rm)->get(route('admin.rm-performance'))->assertForbidden();
    }
}
