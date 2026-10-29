<?php

namespace Tests\Feature;

use App\Models\Lso;
use App\Models\LsoPayment;
use App\Models\Requisition;
use App\Models\RequisitionPayment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Agent 360 pays RMs: it reads what is owed (/payables) and records each completed payment back, so the requisition
 * balance counts it and Westport never pays it again. AGENT360_PAYMENTS switches Westport's own Pay off.
 */
class Agent360PaymentsApiTest extends TestCase
{
    use RefreshDatabase;

    private array $headers = ['Authorization' => 'Bearer test-key-123'];

    private User $rm;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.agent360.key' => 'test-key-123']);
        $this->admin = User::factory()->create(['role' => User::ROLE_OFFICE_ADMIN, 'phone_number' => '254700000001']);
        $this->rm = User::factory()->create(['role' => User::ROLE_RM, 'name' => 'Grace Wambui', 'phone_number' => '254712345678', 'is_active' => true]);
    }

    private function requisition(array $recipients = ['254712345678', '254733333333']): Requisition
    {
        return Requisition::create([
            'requester_id' => $this->rm->id, 'institution_visiting' => 'Treasury', 'working_day' => now()->toDateString(),
            'recipient_phone_numbers' => $recipients,
            'transport_requested_at' => now(), 'transport_amount_requested' => 1500, 'transport_status' => Requisition::STATUS_APPROVED,
            'transport_approved_by_id' => $this->admin->id, 'transport_approved_at' => now(),
            'airtime_requested_at' => now(), 'airtime_amount_requested' => 250,
        ]);
    }

    private function record(Requisition $requisition, array $overrides = []): TestResponse
    {
        return $this->postJson("/api/agent360/v1/requisitions/{$requisition->id}/payments", [
            'category' => 'transport', 'phone' => '254712345678', 'amount_minor' => 150000, 'payment_key' => 'a360-7', 'reference' => 'A360-7', ...$overrides,
        ], $this->headers);
    }

    public function test_it_lists_what_is_owed_per_recipient_and_the_rm_commission_on_confirmed_lso_payments(): void
    {
        $requisition = $this->requisition();
        $lso = Lso::create([
            'reference_number' => 'LSO-001', 'user_id' => $this->rm->id, 'customer_name' => 'Acme', 'original_amount_minor' => 999_999_999,
            'issue_date' => now(), 'document_path' => 'x.pdf', 'document_original_filename' => 'x.pdf', 'document_mime' => 'application/pdf', 'created_by' => $this->rm->id,
        ]);
        $confirmed = LsoPayment::create(['lso_id' => $lso->id, 'amount_minor' => 1_000_000, 'collected_at' => now(), 'status' => LsoPayment::STATUS_CONFIRMED, 'recorded_by' => $this->rm->id]);
        LsoPayment::create(['lso_id' => $lso->id, 'amount_minor' => 500_000, 'collected_at' => now(), 'status' => LsoPayment::STATUS_RECORDED, 'recorded_by' => $this->rm->id]);

        $response = $this->getJson('/api/agent360/v1/payables', $this->headers)->assertOk();

        // Transport for two recipients; airtime is still pending
        $this->assertSame([
            ["req:{$requisition->id}:transport:254712345678", 150000, false],
            ["req:{$requisition->id}:transport:254733333333", 150000, false],
        ], collect($response->json('requisitions'))->map(fn ($l) => [$l['key'], $l['amount_minor'], $l['busy']])->all());
        $this->assertSame($this->rm->id, $response->json('requisitions.0.requester_id'));

        $this->assertSame([["lso_payment:{$confirmed->id}", 100000, $this->rm->id]], collect($response->json('commissions'))->map(fn ($l) => [$l['key'], $l['amount_minor'], $l['agent_id']])->all());
    }

    public function test_a_payment_agent_360_made_counts_against_the_balance_once(): void
    {
        $requisition = $this->requisition();

        $this->record($requisition)->assertCreated()->assertJsonPath('data.status', RequisitionPayment::STATUS_COMPLETED);
        $this->record($requisition)->assertOk(); // the same payment again: recorded once

        $this->assertSame('1500.00', $requisition->fresh()->transport_paid_amount);
        $this->assertDatabaseCount('requisition_payments', 1);
        $this->assertSame(['254733333333'], collect($this->getJson('/api/agent360/v1/payables', $this->headers)->json('requisitions'))->pluck('phone')->all());

        // Already paid: another Agent 360 payment for the same recipient is refused
        $this->record($requisition, ['payment_key' => 'a360-8'])->assertStatus(409);
        // More than owed, or a number not on the requisition
        $this->record($requisition, ['payment_key' => 'a360-9', 'phone' => '254733333333', 'amount_minor' => 200000])->assertStatus(409);
        $this->record($requisition, ['payment_key' => 'a360-10', 'phone' => '254799999999'])->assertStatus(409);

        // Nawiri reversed it: owed again
        $this->postJson('/api/agent360/v1/requisitions/payments/a360-7/reverse', ['reason' => 'Reversed'], $this->headers)->assertOk()->assertJsonPath('data.status', 'failed');
        $this->assertSame('0.00', $requisition->fresh()->transport_paid_amount);
        $this->assertCount(2, $this->getJson('/api/agent360/v1/payables', $this->headers)->json('requisitions'));
    }

    public function test_a_recipient_with_a_westport_payment_in_progress_is_marked_busy_and_not_recorded(): void
    {
        $requisition = $this->requisition(['254712345678']);
        RequisitionPayment::create([
            'requisition_id' => $requisition->id, 'category' => 'transport', 'amount_minor' => 150000, 'phone_number' => '254712345678',
            'provider' => 'NAWIRI_WALLET', 'status' => RequisitionPayment::STATUS_SUBMITTED, 'idempotency_key' => 'circular-req-x',
        ]);

        $this->assertTrue($this->getJson('/api/agent360/v1/payables', $this->headers)->json('requisitions.0.busy'));
        $this->record($requisition)->assertStatus(409);
    }

    public function test_the_switch_turns_off_westports_own_pay(): void
    {
        $requisition = $this->requisition();
        $this->assertTrue($this->admin->canPayRequisition($requisition));

        config(['services.agent360.payments' => true]);
        $this->assertFalse($this->admin->canPayRequisition($requisition));
        $this->actingAs($this->admin)->post(route('admin.requisitions.transport.pay', $requisition))->assertForbidden();
    }
}
