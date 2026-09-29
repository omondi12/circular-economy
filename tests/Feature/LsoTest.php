<?php

namespace Tests\Feature;

use App\Models\Lso;
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

    public function test_rm_can_record_an_lso_with_a_private_document(): void
    {
        $rm = $this->makeRm();
        $file = UploadedFile::fake()->create('lso.pdf', 500, 'application/pdf');

        $response = $this->actingAs($rm)->post(route('rm.lsos.store'), [
            'reference_number' => 'LSO/2026/001',
            'customer_name' => 'Acme Transport Ltd',
            'original_amount' => 1000000,
            'issue_date' => now()->toDateString(),
            'document' => $file,
        ]);

        $response->assertRedirect(route('rm.lsos.index'));

        $lso = Lso::first();
        $this->assertSame($rm->id, $lso->user_id);
        $this->assertSame(100000000, $lso->original_amount_minor);
        $this->assertSame(Lso::STATUS_RECORDED, $lso->status);
        Storage::disk('local')->assertExists($lso->document_path);

        // Never served through the public disk/route.
        $this->assertStringNotContainsString('public', $lso->document_path);
    }

    public function test_store_requires_a_document(): void
    {
        $rm = $this->makeRm();

        $this->actingAs($rm)->post(route('rm.lsos.store'), [
            'reference_number' => 'LSO/2026/002',
            'customer_name' => 'Acme Transport Ltd',
            'original_amount' => 500000,
            'issue_date' => now()->toDateString(),
        ])->assertSessionHasErrors('document');

        $this->assertDatabaseCount('lsos', 0);
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
}
