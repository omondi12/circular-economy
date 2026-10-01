<?php

namespace Tests\Feature;

use App\Models\GovernmentEntity;
use App\Models\Lso;
use App\Models\LsoLot;
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

    private function makeMinistry(): GovernmentEntity
    {
        return GovernmentEntity::create(['name' => 'Ministry of Testing', 'level' => GovernmentEntity::LEVEL_MINISTRY, 'type' => 'ministry']);
    }

    private function lot2CollectionPayload(GovernmentEntity $ministry, array $overrides = []): array
    {
        return array_merge([
            'entity_type' => 'ministry',
            'ministry_id' => $ministry->id,
            'contact_person_name' => 'Jane Doe',
            'contact_person_number' => '0712345678',
            'lot' => 2,
            'category' => 'medical_waste',
            'subcategory' => 'sharps',
            'unit' => 'kg',
            'quantity' => 1000,
            'collection_date' => now()->toDateString(),
        ], $overrides);
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

    // --- Lot 2 confirmation workflow ------------------------------------

    public function test_lot2_recorded_but_not_confirmed_has_no_rm_commission(): void
    {
        $rm = $this->makeRm();
        $ministry = $this->makeMinistry();

        // 1,000 Kg medical waste x KES 200/Kg = KES 200,000 expected.
        $this->actingAs($rm)->post(route('rm.collections.store'), $this->lot2CollectionPayload($ministry))->assertRedirect();

        $lot = LsoLot::first();

        $this->assertSame(LsoLot::STATUS_PENDING, $lot->status);
        $this->assertSame(20_000_000, $lot->expected_revenue_minor); // KES 200,000 estimate exists
        $this->assertNull($lot->confirmed_revenue_minor);
        $this->assertNull($lot->rmCommissionMinor());
    }

    public function test_lot2_confirmed_locks_in_contractual_revenue_and_rm_commission(): void
    {
        $rm = $this->makeRm();
        $admin = $this->makeAdmin();
        $ministry = $this->makeMinistry();

        $this->actingAs($rm)->post(route('rm.collections.store'), $this->lot2CollectionPayload($ministry))->assertRedirect();
        $lot = LsoLot::first();

        $this->actingAs($admin)->post(route('admin.lso-lots.confirm', $lot))->assertRedirect();
        $lot->refresh();

        $this->assertSame(LsoLot::STATUS_CONFIRMED, $lot->status);
        $this->assertSame(20_000_000, $lot->confirmed_revenue_minor); // KES 200,000
        $this->assertSame($admin->id, $lot->confirmed_by);
        $this->assertNotNull($lot->confirmed_at);
        $this->assertSame(2_000_000, $lot->rmCommissionMinor()); // KES 20,000 (10%)
    }

    public function test_rm_cannot_confirm_their_own_lot2_collection(): void
    {
        $rm = $this->makeRm();
        $ministry = $this->makeMinistry();

        $this->actingAs($rm)->post(route('rm.collections.store'), $this->lot2CollectionPayload($ministry))->assertRedirect();
        $lot = LsoLot::first();

        $this->actingAs($rm)->post(route('admin.lso-lots.confirm', $lot))->assertForbidden();

        $this->assertSame(LsoLot::STATUS_PENDING, $lot->fresh()->status);
    }

    /**
     * A second confirm/reject attempt on an already-resolved Lot 2
     * collection must never flip its outcome or overwrite who/when it was
     * decided - the sequential expression of the same guard that protects
     * against two concurrent requests racing each other (see
     * confirmLsoLot()'s lockForUpdate() + re-check-after-lock).
     */
    public function test_confirming_an_already_resolved_lot2_collection_is_blocked_and_never_overwrites_it(): void
    {
        $rm = $this->makeRm();
        $admin = $this->makeAdmin();
        $otherAdmin = $this->makeAdmin('254700000304');
        $ministry = $this->makeMinistry();

        $this->actingAs($rm)->post(route('rm.collections.store'), $this->lot2CollectionPayload($ministry))->assertRedirect();
        $lot = LsoLot::first();

        $this->actingAs($admin)->post(route('admin.lso-lots.confirm', $lot))->assertRedirect();
        $lot->refresh();
        $firstConfirmedBy = $lot->confirmed_by;
        $firstConfirmedAt = $lot->confirmed_at;

        // A second confirm attempt (by a different admin) must not succeed.
        $this->actingAs($otherAdmin)->post(route('admin.lso-lots.confirm', $lot))->assertStatus(422);
        // Nor can it be reversed into a rejection after the fact.
        $this->actingAs($otherAdmin)->post(route('admin.lso-lots.reject', $lot))->assertStatus(422);

        $lot->refresh();
        $this->assertSame(LsoLot::STATUS_CONFIRMED, $lot->status);
        $this->assertSame($firstConfirmedBy, $lot->confirmed_by);
        $this->assertEquals($firstConfirmedAt, $lot->confirmed_at);
        $this->assertSame(20_000_000, $lot->confirmed_revenue_minor);
        $this->assertSame(2_000_000, $lot->rmCommissionMinor());
    }

    public function test_finance_can_confirm_a_lot2_collection_recorded_by_someone_else(): void
    {
        $rm = $this->makeRm();
        $admin = $this->makeAdmin();
        $ministry = $this->makeMinistry();

        $this->actingAs($rm)->post(route('rm.collections.store'), $this->lot2CollectionPayload($ministry))->assertRedirect();
        $lot = LsoLot::first();

        $this->actingAs($admin)->post(route('admin.lso-lots.confirm', $lot))->assertRedirect();

        $this->assertSame(LsoLot::STATUS_CONFIRMED, $lot->fresh()->status);
    }

    public function test_rejecting_a_lot2_collection_never_produces_commission(): void
    {
        $rm = $this->makeRm();
        $admin = $this->makeAdmin();
        $ministry = $this->makeMinistry();

        $this->actingAs($rm)->post(route('rm.collections.store'), $this->lot2CollectionPayload($ministry))->assertRedirect();
        $lot = LsoLot::first();

        $this->actingAs($admin)->post(route('admin.lso-lots.reject', $lot))->assertRedirect();
        $lot->refresh();

        $this->assertSame(LsoLot::STATUS_REJECTED, $lot->status);
        $this->assertNull($lot->rmCommissionMinor());
    }

    /**
     * Two separately confirmed Lot 2 collections (KES 80,000 and KES
     * 50,000 Westport revenue) must produce commission that sums per
     * collection - KES 8,000 + KES 5,000 = KES 13,000 - never a double
     * count or a recombination of the two confirmed amounts.
     */
    public function test_multiple_confirmed_lot2_collections_sum_commission_without_double_counting(): void
    {
        $rm = $this->makeRm();
        $admin = $this->makeAdmin();
        $ministry = $this->makeMinistry();

        // 400kg @ KES 200/Kg = KES 80,000.
        $this->actingAs($rm)->post(route('rm.collections.store'), $this->lot2CollectionPayload($ministry, ['quantity' => 400]))->assertRedirect();
        // 250kg @ KES 200/Kg = KES 50,000.
        $this->actingAs($rm)->post(route('rm.collections.store'), $this->lot2CollectionPayload($ministry, ['quantity' => 250]))->assertRedirect();

        [$lot1, $lot2] = LsoLot::orderBy('id')->get();
        $this->actingAs($admin)->post(route('admin.lso-lots.confirm', $lot1))->assertRedirect();
        $this->actingAs($admin)->post(route('admin.lso-lots.confirm', $lot2))->assertRedirect();

        $lot1->refresh();
        $lot2->refresh();

        $this->assertSame(8_000_000, $lot1->confirmed_revenue_minor); // KES 80,000
        $this->assertSame(5_000_000, $lot2->confirmed_revenue_minor); // KES 50,000
        $this->assertSame(800_000, $lot1->rmCommissionMinor()); // KES 8,000
        $this->assertSame(500_000, $lot2->rmCommissionMinor()); // KES 5,000

        $totalCommission = $lot1->rmCommissionMinor() + $lot2->rmCommissionMinor();
        $this->assertSame(1_300_000, $totalCommission); // KES 13,000
    }

    public function test_pending_lot2_revenue_is_excluded_from_earned_commission_totals(): void
    {
        $rm = $this->makeRm();
        $admin = $this->makeAdmin();
        $ministry = $this->makeMinistry();

        // Confirmed: 400kg -> KES 80,000 revenue -> KES 8,000 commission.
        $this->actingAs($rm)->post(route('rm.collections.store'), $this->lot2CollectionPayload($ministry, ['quantity' => 400]))->assertRedirect();
        // Left pending: 1000kg -> KES 200,000 revenue, must NOT count as earned.
        $this->actingAs($rm)->post(route('rm.collections.store'), $this->lot2CollectionPayload($ministry, ['quantity' => 1000]))->assertRedirect();

        [$confirmedLot, $pendingLot] = LsoLot::orderBy('id')->get();
        $this->actingAs($admin)->post(route('admin.lso-lots.confirm', $confirmedLot))->assertRedirect();

        $this->assertSame(LsoLot::STATUS_PENDING, $pendingLot->fresh()->status);

        // RM's own dashboard shows only the confirmed KES 8,000, not
        // KES 8,000 + a share of the pending KES 200,000.
        $response = $this->actingAs($rm)->get(route('rm.dashboard'))->assertOk();
        $response->assertSee('KES 8,000');
        $response->assertDontSee('KES 28,000'); // would be wrong if pending leaked in
        $response->assertSee('Pending confirmation');

        // Admin RM Performance keeps the two figures visibly separate too.
        $perfResponse = $this->actingAs($admin)->get(route('admin.rm-performance'))->assertOk();
        $row = collect($perfResponse->viewData('rms'))->firstWhere(fn ($r) => $r['rm']->id === $rm->id);
        $this->assertSame(20_000_000, $row['lot2PendingRevenueMinor']); // KES 200,000 pending
        $this->assertSame(8_000_000, $row['lot2ConfirmedRevenueMinor']); // KES 80,000 confirmed
        $this->assertSame(800_000, $row['commissionMinor']); // KES 8,000 earned - the pending amount never enters this
    }

    /**
     * Once confirmed, confirmed_revenue_minor is a stored, locked value -
     * it must never be recomputed live from the collection's current
     * state. Simulated here by mutating the quantity after confirmation
     * (standing in for a future pricing/configuration change) and proving
     * the already-confirmed figures don't move.
     */
    public function test_historical_confirmed_lot2_commission_is_unaffected_by_later_changes(): void
    {
        $rm = $this->makeRm();
        $admin = $this->makeAdmin();
        $ministry = $this->makeMinistry();

        $this->actingAs($rm)->post(route('rm.collections.store'), $this->lot2CollectionPayload($ministry, ['quantity' => 400]))->assertRedirect();
        $lot = LsoLot::first();
        $this->actingAs($admin)->post(route('admin.lso-lots.confirm', $lot))->assertRedirect();
        $lot->refresh();

        $this->assertSame(8_000_000, $lot->confirmed_revenue_minor);
        $this->assertSame(800_000, $lot->rmCommissionMinor());

        // Simulate a later change to the underlying collection (e.g. a
        // correction, or what a future rate change would effectively do).
        $lot->collection->update(['quantity' => 999999]);

        $lot->refresh();
        $this->assertSame(8_000_000, $lot->confirmed_revenue_minor); // unchanged
        $this->assertSame(800_000, $lot->rmCommissionMinor()); // unchanged
    }

    public function test_unmapped_category_cannot_be_confirmed_and_never_produces_commission(): void
    {
        $rm = $this->makeRm();
        $admin = $this->makeAdmin();
        $ministry = $this->makeMinistry();

        // construction_waste has no contractual rate at all.
        $this->actingAs($rm)->post(route('rm.collections.store'), $this->lot2CollectionPayload($ministry, [
            'category' => 'construction_waste', 'subcategory' => 'demolition_debris', 'unit' => 'tonnes', 'quantity' => 10,
        ]))->assertRedirect();

        $lot = LsoLot::first();
        $this->assertNull($lot->expected_revenue_minor);

        $this->actingAs($admin)->post(route('admin.lso-lots.confirm', $lot))
            ->assertSessionHasErrors('lot');

        $lot->refresh();
        $this->assertSame(LsoLot::STATUS_PENDING, $lot->status); // never confirmed
        $this->assertNull($lot->confirmed_revenue_minor);
        $this->assertNull($lot->rmCommissionMinor());
    }
}
