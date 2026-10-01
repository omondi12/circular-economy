<?php

namespace Tests\Feature;

use App\Models\Collection;
use App\Models\GovernmentEntity;
use App\Models\Lso;
use App\Models\LsoLot;
use App\Models\LsoPayment;
use App\Models\RmTarget;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class LsoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
    }

    private function makeRm(string $phone = '254700000101'): User
    {
        return User::factory()->create(['role' => User::ROLE_RM, 'phone_number' => $phone, 'is_active' => true]);
    }

    private function makeAdmin(string $phone = '254700000201'): User
    {
        return User::factory()->create(['role' => User::ROLE_ADMIN, 'phone_number' => $phone]);
    }

    private function makeMinistry(): GovernmentEntity
    {
        return GovernmentEntity::create(['name' => 'Ministry of Testing', 'level' => GovernmentEntity::LEVEL_MINISTRY, 'type' => 'ministry']);
    }

    /**
     * Base payload for a valid Lot 1 (Sale) "Record a Collection"
     * submission - LSO fields are recorded as part of this form, not a
     * separate one, so this is also how an Lso row gets created.
     */
    private function lot1CollectionPayload(GovernmentEntity $ministry, array $overrides = []): array
    {
        return array_merge([
            'entity_type' => 'ministry',
            'ministry_id' => $ministry->id,
            'contact_person_name' => 'Jane Doe',
            'contact_person_number' => '0712345678',
            'lot' => 1,
            'category' => 'motor_vehicles',
            'subcategory' => 'cars',
            'unit' => 'units',
            'quantity' => 2,
            'collection_date' => now()->toDateString(),
            'lso_reference_number' => 'LSO/2026/001',
            'lso_amount' => 1000000,
            'lso_document' => UploadedFile::fake()->create('lso.pdf', 500, 'application/pdf'),
        ], $overrides);
    }

    /**
     * Base payload for a valid Lot 2 (Disposal) "Record a Collection"
     * submission - no LSO fields at all (Lot 2 never creates an Lso), but
     * it does get a standalone, contract-priced LsoLot.
     */
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
            'quantity' => 100,
            'collection_date' => now()->toDateString(),
        ], $overrides);
    }

    public function test_rm_recording_a_lot_1_collection_also_creates_a_linked_lso_with_a_private_document(): void
    {
        $rm = $this->makeRm();
        $ministry = $this->makeMinistry();

        $response = $this->actingAs($rm)->post(route('rm.collections.store'), $this->lot1CollectionPayload($ministry));

        $response->assertRedirect(route('rm.dashboard'));

        $lso = Lso::first();
        $this->assertNotNull($lso);
        $this->assertSame($rm->id, $lso->user_id);
        $this->assertSame(100000000, $lso->original_amount_minor);
        $this->assertSame(Lso::STATUS_RECORDED, $lso->status);
        Storage::disk('local')->assertExists($lso->document_path);

        // Never served through the public disk/route.
        $this->assertStringNotContainsString('public', $lso->document_path);

        $collection = Collection::first();
        $this->assertSame($lso->id, $collection->lso_id);

        $this->assertDatabaseCount('lso_lots', 1);
        $lsoLot = LsoLot::first();
        $this->assertSame($collection->id, $lsoLot->collection_id);
        $this->assertNull($lsoLot->rate_minor);
        $this->assertNull($lsoLot->expected_revenue_minor);
    }

    public function test_recording_another_lot_under_an_existing_lso_reference_does_not_ask_for_amount_or_document_again(): void
    {
        $rm = $this->makeRm();
        $ministry = $this->makeMinistry();

        $this->actingAs($rm)->post(route('rm.collections.store'), $this->lot1CollectionPayload($ministry))
            ->assertRedirect(route('rm.dashboard'));

        $lso = Lso::first();
        $originalDocumentPath = $lso->document_path;

        // Second lot under the same LSO reference - no lso_amount/lso_document
        // supplied at all, and it must not be required.
        $this->actingAs($rm)->post(route('rm.collections.store'), $this->lot1CollectionPayload($ministry, [
            'category' => 'ict_electronics',
            'subcategory' => 'laptops',
            'unit' => 'pieces',
            'quantity' => 10,
            'lso_amount' => null,
            'lso_document' => null,
        ]))->assertRedirect(route('rm.dashboard'));

        $this->assertDatabaseCount('lsos', 1);
        $this->assertDatabaseCount('collections', 2);
        $this->assertDatabaseCount('lso_lots', 2);

        $lso->refresh();
        // The parent LSO's own value/document are untouched by the second lot.
        $this->assertSame(100000000, $lso->original_amount_minor);
        $this->assertSame($originalDocumentPath, $lso->document_path);

        $this->assertSame(2, $lso->lots()->count());
        $this->assertEqualsCanonicalizing(
            Collection::pluck('id')->all(),
            $lso->lots->pluck('collection_id')->all(),
        );
    }

    public function test_recording_another_lot_under_an_existing_lso_notes_that_its_value_and_document_are_unchanged(): void
    {
        $rm = $this->makeRm();
        $ministry = $this->makeMinistry();

        $this->actingAs($rm)->post(route('rm.collections.store'), $this->lot1CollectionPayload($ministry));
        $lso = Lso::first();

        $this->actingAs($rm)->post(route('rm.collections.store'), $this->lot1CollectionPayload($ministry, [
            'lso_amount' => null,
            'lso_document' => null,
        ]))->assertRedirect(route('rm.dashboard'))
            ->assertSessionHas('status', "Collection recorded and added to LSO {$lso->reference_number}. Its original amount and document are unchanged.");
    }

    public function test_a_resubmitted_amount_that_does_not_match_the_existing_lso_is_rejected_not_silently_dropped(): void
    {
        $rm = $this->makeRm();
        $ministry = $this->makeMinistry();

        $this->actingAs($rm)->post(route('rm.collections.store'), $this->lot1CollectionPayload($ministry, [
            'lso_amount' => 1000000,
        ]));
        $lso = Lso::first();
        $this->assertSame(100000000, $lso->original_amount_minor);

        // A second lot under the same reference, but with a DIFFERENT
        // amount typed in - must be rejected, not silently discarded, since
        // it's a real discrepancy (wrong reference, stale form, etc.).
        $this->actingAs($rm)->post(route('rm.collections.store'), $this->lot1CollectionPayload($ministry, [
            'lso_amount' => 2000000,
            'lso_document' => null,
        ]))->assertSessionHasErrors('lso_amount');

        $this->assertDatabaseCount('collections', 1);
        $this->assertDatabaseCount('lso_lots', 1);
        $this->assertSame(100000000, $lso->fresh()->original_amount_minor);
    }

    public function test_an_rm_cannot_add_a_lot_to_another_rms_lso_by_reusing_its_reference_number(): void
    {
        $rmA = $this->makeRm('254700000601');
        $rmB = $this->makeRm('254700000602');
        $ministry = $this->makeMinistry();

        $this->actingAs($rmA)->post(route('rm.collections.store'), $this->lot1CollectionPayload($ministry))
            ->assertRedirect(route('rm.dashboard'));

        $lso = Lso::first();

        $this->actingAs($rmB)->post(route('rm.collections.store'), $this->lot1CollectionPayload($ministry, [
            'lso_amount' => null,
            'lso_document' => null,
        ]))->assertSessionHasErrors('lso_reference_number');

        $this->assertDatabaseCount('lsos', 1);
        $this->assertDatabaseCount('lso_lots', 1);
        $this->assertSame($rmA->id, $lso->fresh()->user_id);
    }

    public function test_a_lot_cannot_be_added_to_an_lso_recorded_against_a_different_ministry(): void
    {
        $rm = $this->makeRm();
        $ministryA = $this->makeMinistry();
        $ministryB = GovernmentEntity::create(['name' => 'Ministry of Other Things', 'level' => GovernmentEntity::LEVEL_MINISTRY, 'type' => 'ministry']);

        $this->actingAs($rm)->post(route('rm.collections.store'), $this->lot1CollectionPayload($ministryA))
            ->assertRedirect(route('rm.dashboard'));
        $lso = Lso::first();

        $this->actingAs($rm)->post(route('rm.collections.store'), $this->lot1CollectionPayload($ministryB, [
            'lso_amount' => null,
            'lso_document' => null,
        ]))->assertSessionHasErrors('lso_reference_number');

        $this->assertDatabaseCount('collections', 1);
        $this->assertDatabaseCount('lso_lots', 1);
        $this->assertSame($ministryA->id, $lso->fresh()->ministry_id);
    }

    public function test_no_more_lots_can_be_added_to_a_fully_paid_lso(): void
    {
        $rm = $this->makeRm();
        $ministry = $this->makeMinistry();

        $this->actingAs($rm)->post(route('rm.collections.store'), $this->lot1CollectionPayload($ministry));
        $lso = Lso::first();
        $lso->update(['status' => Lso::STATUS_FULLY_PAID]);

        $this->actingAs($rm)->post(route('rm.collections.store'), $this->lot1CollectionPayload($ministry, [
            'lso_amount' => null,
            'lso_document' => null,
        ]))->assertSessionHasErrors('lso_reference_number');

        $this->assertDatabaseCount('collections', 1);
        $this->assertDatabaseCount('lso_lots', 1);
    }

    public function test_lot_1_collection_requires_an_lso_reference_number(): void
    {
        $rm = $this->makeRm();
        $ministry = $this->makeMinistry();

        $this->actingAs($rm)->post(route('rm.collections.store'), $this->lot1CollectionPayload($ministry, [
            'lso_reference_number' => null,
        ]))->assertSessionHasErrors('lso_reference_number');

        $this->assertDatabaseCount('lsos', 0);
    }

    public function test_a_brand_new_lso_reference_requires_an_amount(): void
    {
        $rm = $this->makeRm();
        $ministry = $this->makeMinistry();

        $this->actingAs($rm)->post(route('rm.collections.store'), $this->lot1CollectionPayload($ministry, [
            'lso_amount' => null,
        ]))->assertSessionHasErrors('lso_amount');

        $this->assertDatabaseCount('lsos', 0);
    }

    public function test_a_brand_new_lso_reference_requires_a_document(): void
    {
        $rm = $this->makeRm();
        $ministry = $this->makeMinistry();

        $this->actingAs($rm)->post(route('rm.collections.store'), $this->lot1CollectionPayload($ministry, [
            'lso_document' => null,
        ]))->assertSessionHasErrors('lso_document');

        $this->assertDatabaseCount('lsos', 0);
    }

    public function test_lot_2_collection_never_creates_an_lso(): void
    {
        $rm = $this->makeRm();
        $ministry = $this->makeMinistry();

        $response = $this->actingAs($rm)->post(route('rm.collections.store'), [
            'entity_type' => 'ministry',
            'ministry_id' => $ministry->id,
            'contact_person_name' => 'Jane Doe',
            'contact_person_number' => '0712345678',
            'lot' => 2,
            'category' => 'medical_waste',
            'subcategory' => 'sharps',
            'unit' => 'kg',
            'quantity' => 5,
            'collection_date' => now()->toDateString(),
        ]);

        $response->assertRedirect(route('rm.dashboard'));
        $this->assertDatabaseCount('lsos', 0);

        $collection = Collection::first();
        $this->assertNull($collection->lso_id);
    }

    public function test_lot_2_collection_is_priced_using_the_contractual_per_unit_rate(): void
    {
        $rm = $this->makeRm();
        $ministry = $this->makeMinistry();

        // medical_waste -> the contract's "Medical and Industrial waste"
        // bucket, KES 200/Kg. 100kg -> KES 20,000 = 2,000,000 minor.
        $this->actingAs($rm)->post(route('rm.collections.store'), $this->lot2CollectionPayload($ministry))
            ->assertRedirect(route('rm.dashboard'));

        $collection = Collection::first();
        $this->assertNull($collection->lso_id); // still no Lso for Lot 2, unchanged.

        $lsoLot = $collection->lsoLot;
        $this->assertNotNull($lsoLot);
        $this->assertSame(20_000, $lsoLot->rate_minor);
        $this->assertSame(2_000_000, $lsoLot->expected_revenue_minor);
    }

    public function test_lot_2_collection_with_an_unmapped_category_is_left_unpriced_not_guessed(): void
    {
        $rm = $this->makeRm();
        $ministry = $this->makeMinistry();

        // construction_waste has no contractual rate at all.
        $this->actingAs($rm)->post(route('rm.collections.store'), $this->lot2CollectionPayload($ministry, [
            'category' => 'construction_waste',
            'subcategory' => 'demolition_debris',
            'unit' => 'tonnes',
        ]))->assertRedirect(route('rm.dashboard'));

        $lsoLot = Collection::first()->lsoLot;
        $this->assertNotNull($lsoLot);
        $this->assertNull($lsoLot->rate_minor);
        $this->assertNull($lsoLot->expected_revenue_minor);
    }

    public function test_lot_2_collection_with_an_incompatible_unit_is_left_unpriced_not_guessed(): void
    {
        $rm = $this->makeRm();
        $ministry = $this->makeMinistry();

        // ewaste is a valid app unit choice in tonnes, but the contract
        // only prices e-waste per Kg - no conversion is invented.
        $this->actingAs($rm)->post(route('rm.collections.store'), $this->lot2CollectionPayload($ministry, [
            'category' => 'ewaste',
            'subcategory' => 'computers_it',
            'unit' => 'tonnes',
        ]))->assertRedirect(route('rm.dashboard'));

        $lsoLot = Collection::first()->lsoLot;
        $this->assertNotNull($lsoLot);
        $this->assertNull($lsoLot->rate_minor);
        $this->assertNull($lsoLot->expected_revenue_minor);
    }

    public function test_lot_2_shared_bucket_categories_use_the_same_contractual_rate(): void
    {
        $rm = $this->makeRm();
        $ministry = $this->makeMinistry();

        // industrial_hazardous shares the contract's "Medical and
        // Industrial waste" bucket with medical_waste - same KES 200/Kg.
        $this->actingAs($rm)->post(route('rm.collections.store'), $this->lot2CollectionPayload($ministry, [
            'category' => 'industrial_hazardous',
            'subcategory' => 'chemical_waste',
            'unit' => 'kg',
        ]))->assertRedirect(route('rm.dashboard'));

        $lsoLot = Collection::first()->lsoLot;
        $this->assertSame(20_000, $lsoLot->rate_minor);
        $this->assertSame(2_000_000, $lsoLot->expected_revenue_minor);
    }

    private function createLso(User $rm, int $originalAmountMinor = 100000000): Lso
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

    public function test_rm_cannot_confirm_their_own_payment(): void
    {
        $rm = $this->makeRm();
        $lso = $this->createLso($rm);

        $this->actingAs($rm)->post(route('rm.lsos.payments.store', $lso), [
            'amount' => 200000,
            'collected_at' => now()->toDateString(),
        ])->assertRedirect();

        $payment = LsoPayment::first();
        $this->assertSame(LsoPayment::STATUS_RECORDED, $payment->status);

        $this->actingAs($rm)->post(route('admin.lso-payments.confirm', $payment))->assertForbidden();
    }

    public function test_recording_a_payment_with_a_plain_amount_is_unaffected_by_the_commission_calculator(): void
    {
        $rm = $this->makeRm();
        $lso = $this->createLso($rm);

        $this->actingAs($rm)->post(route('rm.lsos.payments.store', $lso), [
            'amount' => 200000,
            'collected_at' => now()->toDateString(),
        ])->assertRedirect();

        $payment = LsoPayment::first();
        $this->assertSame(20_000_000, $payment->amount_minor);
        $this->assertNull($payment->gross_amount_minor);
        $this->assertNull($payment->netAmountMinor());
    }

    public function test_submitting_both_amount_and_gross_amount_is_rejected_not_silently_resolved(): void
    {
        $rm = $this->makeRm();
        $lso = $this->createLso($rm);

        $this->actingAs($rm)->post(route('rm.lsos.payments.store', $lso), [
            'amount' => 200000,
            'gross_amount' => 150000,
            'collected_at' => now()->toDateString(),
        ])->assertSessionHasErrors('amount');

        $this->assertDatabaseCount('lso_payments', 0);
    }

    public function test_recording_a_payment_with_a_gross_sale_value_computes_the_tiered_commission(): void
    {
        $rm = $this->makeRm();
        $lso = $this->createLso($rm);

        // KES 150,000 gross sale value -> KES 13,500 contractual commission.
        $this->actingAs($rm)->post(route('rm.lsos.payments.store', $lso), [
            'gross_amount' => 150000,
            'collected_at' => now()->toDateString(),
        ])->assertRedirect();

        $payment = LsoPayment::first();
        $this->assertSame(15_000_000, $payment->gross_amount_minor);
        $this->assertSame(1_350_000, $payment->amount_minor);
        $this->assertSame(15_000_000 - 1_350_000, $payment->netAmountMinor());
    }

    /**
     * Critical regression: two KES 80,000 gross payments against one LSO
     * must total KES 14,200 commission (10% of the first 100,000 of the
     * true 160,000 cumulative, 7% of the rest) once both are confirmed -
     * never KES 16,000 (10% applied independently to each payment, which
     * would double-dip the tier). Exercised through the real HTTP
     * endpoints end-to-end, not just the pricing service directly.
     */
    public function test_two_gross_payments_never_double_dip_the_lot1_tier_once_confirmed(): void
    {
        $rm = $this->makeRm();
        $admin = $this->makeAdmin();
        $lso = $this->createLso($rm, 999_999_999_999);

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

        $this->assertSame(800_000, $payment1->amount_minor); // 10% of the first 80,000
        $this->assertSame(620_000, $payment2->amount_minor); // remaining 20,000 at 10% + 60,000 at 7%

        $totalCommission = $payment1->amount_minor + $payment2->amount_minor;
        $this->assertSame(1_420_000, $totalCommission); // KES 14,200
        $this->assertNotSame(1_600_000, $totalCommission); // the wrong, naive answer
        $this->assertSame($totalCommission, $lso->fresh()->confirmedCollectedMinor());
    }

    /**
     * KES 100,000 then KES 50,000: the second payment must be charged
     * entirely at 7% once confirmed (the threshold is already used up by
     * the first), not another partial 10% slice.
     */
    public function test_a_payment_above_an_already_confirmed_threshold_is_charged_entirely_at_the_second_tier(): void
    {
        $rm = $this->makeRm();
        $admin = $this->makeAdmin();
        $lso = $this->createLso($rm, 999_999_999_999);

        $this->actingAs($rm)->post(route('rm.lsos.payments.store', $lso), [
            'gross_amount' => 100000, 'collected_at' => now()->toDateString(),
        ])->assertRedirect();
        $payment1 = LsoPayment::first();
        $this->actingAs($admin)->post(route('admin.lso-payments.confirm', $payment1))->assertRedirect();

        // Recorded AFTER payment1 is already confirmed, so its provisional
        // estimate at record time should already reflect the used-up tier.
        $this->actingAs($rm)->post(route('rm.lsos.payments.store', $lso), [
            'gross_amount' => 50000, 'collected_at' => now()->toDateString(),
        ])->assertRedirect();
        $payment2 = LsoPayment::latest('id')->first();

        $this->assertSame(350_000, $payment2->amount_minor); // provisional, already correct: 7% of 50,000

        $this->actingAs($admin)->post(route('admin.lso-payments.confirm', $payment2))->assertRedirect();
        $payment2->refresh();

        $this->assertSame(350_000, $payment2->amount_minor); // unchanged by confirmation
        $this->assertSame(1_350_000, $payment1->fresh()->amount_minor + $payment2->amount_minor); // KES 13,500 total
    }

    public function test_admin_confirming_a_payment_updates_lso_status_and_never_double_counts_the_original_value(): void
    {
        $rm = $this->makeRm();
        $admin = $this->makeAdmin();
        $lso = $this->createLso($rm, 100000000); // KES 1,000,000

        $payment1 = LsoPayment::create([
            'lso_id' => $lso->id, 'amount_minor' => 20000000, 'collected_at' => now(),
            'status' => LsoPayment::STATUS_RECORDED, 'recorded_by' => $rm->id,
        ]);
        $payment2 = LsoPayment::create([
            'lso_id' => $lso->id, 'amount_minor' => 30000000, 'collected_at' => now(),
            'status' => LsoPayment::STATUS_RECORDED, 'recorded_by' => $rm->id,
        ]);

        $this->actingAs($admin)->post(route('admin.lso-payments.confirm', $payment1))->assertRedirect();
        $this->actingAs($admin)->post(route('admin.lso-payments.confirm', $payment2))->assertRedirect();

        $lso->refresh();
        // Original value must stay exactly what it was - never inflated by
        // having two payment rows joined against it.
        $this->assertSame(100000000, $lso->original_amount_minor);
        $this->assertSame(50000000, $lso->confirmedCollectedMinor());
        $this->assertSame(50000000, $lso->outstandingMinor());
        $this->assertSame(Lso::STATUS_ACTIVE, $lso->status);
        $this->assertFalse($lso->isFullyPaid());
    }

    public function test_lso_becomes_fully_paid_only_once_confirmed_payments_reach_the_original_amount(): void
    {
        $rm = $this->makeRm();
        $admin = $this->makeAdmin();
        $lso = $this->createLso($rm, 50000000);

        $payment = LsoPayment::create([
            'lso_id' => $lso->id, 'amount_minor' => 50000000, 'collected_at' => now(),
            'status' => LsoPayment::STATUS_RECORDED, 'recorded_by' => $rm->id,
        ]);

        $this->actingAs($admin)->post(route('admin.lso-payments.confirm', $payment))->assertRedirect();

        $lso->refresh();
        $this->assertSame(Lso::STATUS_FULLY_PAID, $lso->status);
        $this->assertTrue($lso->isFullyPaid());
        $this->assertSame(0, $lso->outstandingMinor());
    }

    public function test_confirming_a_payment_that_would_exceed_the_original_amount_is_blocked(): void
    {
        $rm = $this->makeRm();
        $admin = $this->makeAdmin();
        $lso = $this->createLso($rm, 10000000);

        $payment = LsoPayment::create([
            'lso_id' => $lso->id, 'amount_minor' => 20000000, 'collected_at' => now(),
            'status' => LsoPayment::STATUS_RECORDED, 'recorded_by' => $rm->id,
        ]);

        $this->actingAs($admin)->post(route('admin.lso-payments.confirm', $payment))
            ->assertSessionHasErrors('payment');

        $this->assertSame(LsoPayment::STATUS_RECORDED, $payment->fresh()->status);
    }

    /**
     * The ceiling guard must compare against the realized GROSS sale value
     * for a gross-based Lot 1 payment, not the much smaller commission
     * stored in amount_minor - otherwise a sale far larger than the LSO's
     * appraised value would sail through because its 7-10% commission
     * slice alone never reaches original_amount_minor.
     */
    public function test_confirming_a_gross_based_payment_is_blocked_when_the_sale_value_exceeds_the_original_amount(): void
    {
        $rm = $this->makeRm();
        $admin = $this->makeAdmin();
        $lso = $this->createLso($rm, 5_000_000); // KES 50,000 appraised value

        // KES 60,000 gross sale -> only KES 6,000 commission (10%), which
        // alone would never exceed the KES 50,000 ceiling - but the sale
        // itself already has.
        $this->actingAs($rm)->post(route('rm.lsos.payments.store', $lso), [
            'gross_amount' => 60000, 'collected_at' => now()->toDateString(),
        ])->assertRedirect();
        $payment = LsoPayment::first();
        $this->assertSame(600_000, $payment->amount_minor); // commission alone would pass the old check

        $this->actingAs($admin)->post(route('admin.lso-payments.confirm', $payment))
            ->assertSessionHasErrors('payment');

        $this->assertSame(LsoPayment::STATUS_RECORDED, $payment->fresh()->status);
    }

    public function test_rejected_payments_never_count_toward_collected_totals(): void
    {
        $rm = $this->makeRm();
        $admin = $this->makeAdmin();
        $lso = $this->createLso($rm, 10000000);

        $payment = LsoPayment::create([
            'lso_id' => $lso->id, 'amount_minor' => 5000000, 'collected_at' => now(),
            'status' => LsoPayment::STATUS_RECORDED, 'recorded_by' => $rm->id,
        ]);

        $this->actingAs($admin)->post(route('admin.lso-payments.reject', $payment))->assertRedirect();

        $this->assertSame(LsoPayment::STATUS_REJECTED, $payment->fresh()->status);
        $this->assertSame(0, $lso->fresh()->confirmedCollectedMinor());
        $this->assertSame(Lso::STATUS_RECORDED, $lso->fresh()->status);
    }

    public function test_operations_cannot_confirm_or_reject_a_payment_but_can_view_the_finance_index(): void
    {
        $rm = $this->makeRm();
        $operations = User::factory()->create(['role' => User::ROLE_OPERATIONS, 'phone_number' => '254700000301']);
        $lso = $this->createLso($rm);
        $payment = LsoPayment::create([
            'lso_id' => $lso->id, 'amount_minor' => 1000000, 'collected_at' => now(),
            'status' => LsoPayment::STATUS_RECORDED, 'recorded_by' => $rm->id,
        ]);

        $this->actingAs($operations)->post(route('admin.lso-payments.confirm', $payment))->assertForbidden();
        $this->actingAs($operations)->get(route('admin.lsos.index'))->assertOk();
    }

    public function test_an_rm_cannot_view_or_record_a_payment_on_another_rms_lso(): void
    {
        $rmA = $this->makeRm('254700000401');
        $rmB = $this->makeRm('254700000402');
        $lso = $this->createLso($rmA);

        $this->actingAs($rmB)->get(route('rm.lsos.show', $lso))->assertForbidden();
        $this->actingAs($rmB)->post(route('rm.lsos.payments.store', $lso), [
            'amount' => 1000, 'collected_at' => now()->toDateString(),
        ])->assertForbidden();
    }

    public function test_lso_document_is_private_and_only_visible_to_the_owning_rm_or_finance_roles(): void
    {
        $rmA = $this->makeRm('254700000501');
        $rmB = $this->makeRm('254700000502');
        $admin = $this->makeAdmin();
        $lso = $this->createLso($rmA);
        Storage::disk('local')->put($lso->document_path, 'fake-pdf-bytes');

        $this->actingAs($rmA)->get(route('lso.document.show', $lso))->assertOk();
        $this->actingAs($rmB)->get(route('lso.document.show', $lso))->assertForbidden();
        $this->actingAs($admin)->get(route('lso.document.show', $lso))->assertOk();
    }

    public function test_monetary_target_achievement_counts_only_confirmed_payments_within_the_period_not_the_lso_value(): void
    {
        $rm = $this->makeRm();
        $admin = $this->makeAdmin();
        $lso = $this->createLso($rm, 200000000); // KES 2,000,000 stated value

        $target = RmTarget::create([
            'user_id' => $rm->id,
            'type' => RmTarget::TYPE_MONETARY,
            'target_amount_minor' => 50000000, // KES 500,000 target
            'period_start' => now()->startOfMonth(),
            'period_end' => now()->endOfMonth(),
            'created_by' => $admin->id,
        ]);

        $payment = LsoPayment::create([
            'lso_id' => $lso->id, 'amount_minor' => 25000000, 'collected_at' => now(),
            'status' => LsoPayment::STATUS_CONFIRMED, 'recorded_by' => $rm->id, 'confirmed_by' => $admin->id,
        ]);

        // Only the confirmed payment (KES 250,000) counts, never the LSO's
        // stated KES 2,000,000 value.
        $this->assertSame(25000000, $target->fresh()->actualAmountMinor());
        $this->assertSame(50.0, $target->fresh()->achievementPercent());
    }

    /**
     * Regression test: period_end is a 'date' cast attribute, so PHP
     * truncates it to midnight the instant it's read, even though the DB
     * column keeps a full timestamp. A payment collected on the period's
     * last calendar day, at any time other than exactly 00:00:00, must
     * still count - it was silently excluded before actualAmountMinor()
     * switched from whereBetween() to whereDate().
     */
    public function test_monetary_target_counts_a_payment_collected_later_in_the_day_on_the_periods_last_date(): void
    {
        $rm = $this->makeRm();
        $admin = $this->makeAdmin();
        $lso = $this->createLso($rm, 100000000);

        $periodEnd = now()->endOfMonth();
        $target = RmTarget::create([
            'user_id' => $rm->id,
            'type' => RmTarget::TYPE_MONETARY,
            'target_amount_minor' => 10000000,
            'period_start' => now()->startOfMonth(),
            'period_end' => $periodEnd,
            'created_by' => $admin->id,
        ]);

        LsoPayment::create([
            'lso_id' => $lso->id,
            'amount_minor' => 10000000,
            // The period's last calendar day, well after midnight.
            'collected_at' => $periodEnd->copy()->setTime(18, 30),
            'status' => LsoPayment::STATUS_CONFIRMED, 'recorded_by' => $rm->id, 'confirmed_by' => $admin->id,
        ]);

        $this->assertSame(10000000, $target->fresh()->actualAmountMinor());
    }

    /**
     * Year-end boundary: a target period ending 31 Dec must count a
     * payment collected that same day, and must NOT count one collected
     * 1 Jan of the following year - whereDate() must not accidentally
     * cross the year rollover either direction.
     */
    public function test_monetary_target_handles_the_year_end_boundary_correctly(): void
    {
        $rm = $this->makeRm();
        $admin = $this->makeAdmin();
        $lso = $this->createLso($rm, 100000000);

        $target = RmTarget::create([
            'user_id' => $rm->id,
            'type' => RmTarget::TYPE_MONETARY,
            'target_amount_minor' => 10000000,
            'period_start' => '2026-12-01',
            'period_end' => '2026-12-31',
            'created_by' => $admin->id,
        ]);

        LsoPayment::create([
            'lso_id' => $lso->id, 'amount_minor' => 5000000,
            'collected_at' => '2026-12-31 22:00:00', // last day of the period, late in the day
            'status' => LsoPayment::STATUS_CONFIRMED, 'recorded_by' => $rm->id, 'confirmed_by' => $admin->id,
        ]);
        LsoPayment::create([
            'lso_id' => $lso->id, 'amount_minor' => 9999999,
            'collected_at' => '2027-01-01 00:30:00', // just over into the new year
            'status' => LsoPayment::STATUS_CONFIRMED, 'recorded_by' => $rm->id, 'confirmed_by' => $admin->id,
        ]);

        // Only the 31 Dec payment counts - the 1 Jan one is outside the period.
        $this->assertSame(5000000, $target->fresh()->actualAmountMinor());
    }

    public function test_count_target_only_counts_an_lso_once_it_is_fully_paid_within_the_period(): void
    {
        $rm = $this->makeRm();
        $admin = $this->makeAdmin();
        $lsoFullyPaid = $this->createLso($rm, 10000000);
        $lsoPartiallyPaid = $this->createLso($rm, 10000000);

        LsoPayment::create([
            'lso_id' => $lsoFullyPaid->id, 'amount_minor' => 10000000, 'collected_at' => now(),
            'status' => LsoPayment::STATUS_CONFIRMED, 'recorded_by' => $rm->id, 'confirmed_by' => $admin->id,
        ]);
        LsoPayment::create([
            'lso_id' => $lsoPartiallyPaid->id, 'amount_minor' => 5000000, 'collected_at' => now(),
            'status' => LsoPayment::STATUS_CONFIRMED, 'recorded_by' => $rm->id, 'confirmed_by' => $admin->id,
        ]);

        $target = RmTarget::create([
            'user_id' => $rm->id,
            'type' => RmTarget::TYPE_COUNT,
            'target_count' => 5,
            'period_start' => now()->startOfMonth(),
            'period_end' => now()->endOfMonth(),
            'created_by' => $admin->id,
        ]);

        $this->assertSame(1, $target->actualCount());
    }

    public function test_admin_dashboard_lso_count_reflects_the_visible_lso_scope(): void
    {
        $rmA = $this->makeRm('254700000701');
        $rmB = $this->makeRm('254700000702');
        $admin = $this->makeAdmin();
        $this->createLso($rmA);
        $this->createLso($rmB);

        $response = $this->actingAs($admin)->get(route('admin.dashboard'))->assertOk()->assertSee('LSOs');

        $this->assertSame(2, $response->viewData('lsoCount'));
    }

    public function test_admin_lsos_index_can_be_filtered_by_reference_and_issue_date_range(): void
    {
        $rm = $this->makeRm();
        $admin = $this->makeAdmin();
        $inRange = Lso::create([
            'reference_number' => 'LSO/2026/100', 'user_id' => $rm->id, 'customer_name' => 'Acme',
            'original_amount_minor' => 1000000, 'issue_date' => '2026-06-15',
            'document_path' => 'lso-documents/fake.pdf', 'document_original_filename' => 'fake.pdf',
            'document_mime' => 'application/pdf', 'created_by' => $rm->id,
        ]);
        $outOfRange = Lso::create([
            'reference_number' => 'LSO/2026/200', 'user_id' => $rm->id, 'customer_name' => 'Other Co',
            'original_amount_minor' => 1000000, 'issue_date' => '2026-01-01',
            'document_path' => 'lso-documents/fake2.pdf', 'document_original_filename' => 'fake2.pdf',
            'document_mime' => 'application/pdf', 'created_by' => $rm->id,
        ]);

        $response = $this->actingAs($admin)->get(route('admin.lsos.index', [
            'reference' => 'LSO/2026/100',
            'from' => '2026-06-01',
            'to' => '2026-06-30',
        ]));

        $response->assertOk()->assertSee($inRange->reference_number)->assertDontSee($outOfRange->reference_number);
    }

    public function test_admin_lsos_index_shows_the_lot_count_per_lso(): void
    {
        $rm = $this->makeRm();
        $ministry = $this->makeMinistry();
        $admin = $this->makeAdmin();

        $this->actingAs($rm)->post(route('rm.collections.store'), $this->lot1CollectionPayload($ministry));
        $this->actingAs($rm)->post(route('rm.collections.store'), $this->lot1CollectionPayload($ministry, [
            'category' => 'ict_electronics', 'subcategory' => 'laptops', 'unit' => 'pieces', 'quantity' => 3,
            'lso_amount' => null, 'lso_document' => null,
        ]));

        $lso = Lso::first();

        $response = $this->actingAs($admin)->get(route('admin.lsos.index'))
            ->assertOk()
            ->assertSee($lso->reference_number);

        $row = $response->viewData('lsos')->firstWhere('id', $lso->id);
        $this->assertNotNull($row);
        $this->assertSame(2, $row->lots_count);
    }

    public function test_expected_revenue_is_null_until_every_lot_is_priced(): void
    {
        $rm = $this->makeRm();
        $ministry = $this->makeMinistry();

        $this->actingAs($rm)->post(route('rm.collections.store'), $this->lot1CollectionPayload($ministry));
        $lso = Lso::first();

        $this->assertNull($lso->expectedRevenueMinor());

        $lot = LsoLot::first();
        $lot->update(['rate_minor' => 500000, 'expected_revenue_minor' => 1000000]);

        $this->assertSame(1000000, $lso->fresh()->expectedRevenueMinor());
    }

    public function test_one_lso_with_multiple_lots_and_multiple_payments_never_multiplies_totals(): void
    {
        $rm = $this->makeRm();
        $admin = $this->makeAdmin();
        $ministry = $this->makeMinistry();

        $this->actingAs($rm)->post(route('rm.collections.store'), $this->lot1CollectionPayload($ministry));
        $lso = Lso::first();
        $this->actingAs($rm)->post(route('rm.collections.store'), $this->lot1CollectionPayload($ministry, [
            'category' => 'ict_electronics', 'subcategory' => 'laptops', 'unit' => 'pieces', 'quantity' => 3,
            'lso_amount' => null, 'lso_document' => null,
        ]));

        $this->assertSame(2, $lso->lots()->count());

        $payment1 = LsoPayment::create([
            'lso_id' => $lso->id, 'amount_minor' => 20000000, 'collected_at' => now(),
            'status' => LsoPayment::STATUS_RECORDED, 'recorded_by' => $rm->id,
        ]);
        $payment2 = LsoPayment::create([
            'lso_id' => $lso->id, 'amount_minor' => 30000000, 'collected_at' => now(),
            'status' => LsoPayment::STATUS_RECORDED, 'recorded_by' => $rm->id,
        ]);
        $this->actingAs($admin)->post(route('admin.lso-payments.confirm', $payment1))->assertRedirect();
        $this->actingAs($admin)->post(route('admin.lso-payments.confirm', $payment2))->assertRedirect();

        $lso->refresh();
        // 2 lots x 2 payments must never become 4x anything - the original
        // value is read straight off the lsos row, and confirmed
        // collections are summed once from lso_payments only.
        $this->assertSame(100000000, $lso->original_amount_minor);
        $this->assertSame(50000000, $lso->confirmedCollectedMinor());
        $this->assertSame(50000000, $lso->outstandingMinor());
        $this->assertSame(2, $lso->lots()->count());
    }

    public function test_rm_performance_page_shows_pending_lot2_revenue_and_flags_unpriced_lots_separately(): void
    {
        $rm = $this->makeRm();
        $ministry = $this->makeMinistry();
        $admin = $this->makeAdmin();

        // Priced: medical_waste, 100kg @ KES 200/Kg = KES 20,000 (pending -
        // not yet confirmed by Finance).
        $this->actingAs($rm)->post(route('rm.collections.store'), $this->lot2CollectionPayload($ministry));
        // Unpriced: construction_waste has no contractual rate.
        $this->actingAs($rm)->post(route('rm.collections.store'), $this->lot2CollectionPayload($ministry, [
            'category' => 'construction_waste', 'subcategory' => 'demolition_debris', 'unit' => 'tonnes',
        ]));

        $response = $this->actingAs($admin)->get(route('admin.rm-performance'))->assertOk();

        $row = collect($response->viewData('rms'))->firstWhere(fn ($r) => $r['rm']->id === $rm->id);
        $this->assertSame(2_000_000, $row['lot2PendingRevenueMinor']); // only the priced lot counts
        $this->assertSame(0, $row['lot2ConfirmedRevenueMinor']); // nothing confirmed yet
        $this->assertSame(1, $row['lot2UnpricedCount']);
        $response->assertSee('KES 20,000')->assertSee('1 unpriced');
    }
}
