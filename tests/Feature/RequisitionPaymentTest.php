<?php

namespace Tests\Feature;

use App\Models\NawiriTreasuryCredential;
use App\Models\Requisition;
use App\Models\RequisitionPayment;
use App\Models\User;
use App\Services\RequisitionPaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class RequisitionPaymentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('cache.default', 'array');
        Cache::clear();
        config()->set('services.nawiri_payroll', [
            'base_url' => 'https://nawiri.test',
            'timeout' => 10,
        ]);
        NawiriTreasuryCredential::create([
            'email' => 'payroll@example.com',
            'password' => 'secret-password',
            'pin' => '1234',
            'verified_at' => now(),
        ]);
    }

    public function test_office_admin_pay_button_submits_an_idempotent_nawiri_payout_without_marking_it_paid_early(): void
    {
        [$admin, $requisition] = $this->approvedRequisition();

        Http::fake([
            'https://nawiri.test/api/auth/login' => Http::response([
                'access_token' => 'access-token',
                'expires_in' => 300,
            ]),
            'https://nawiri.test/api/jambopay/business/payroll/wallet' => Http::response([
                'payment' => [
                    'id' => 'c00f5619-2daa-47ad-9a4d-da4686a3cc75',
                    'status' => 'SUBMITTED',
                    'jpRef' => 'JP-REF-1',
                    'jpOrderId' => 'JP-ORDER-1',
                ],
            ]),
        ]);

        $response = $this->actingAs($admin)->post(
            route('admin.requisitions.transport.pay', $requisition),
        );

        $response->assertRedirect()->assertSessionHas('warning');

        $this->actingAs($admin)->post(
            route('admin.requisitions.transport.pay', $requisition),
        )->assertSessionHas('warning');

        $this->assertDatabaseHas('requisition_payments', [
            'requisition_id' => $requisition->id,
            'category' => RequisitionPayment::CATEGORY_TRANSPORT,
            'amount_minor' => 150000,
            'phone_number' => '254733333333',
            'status' => RequisitionPayment::STATUS_SUBMITTED,
        ]);
        $this->assertDatabaseCount('requisition_payments', 1);
        $this->assertSame('0.00', $requisition->fresh()->transport_paid_amount);

        Http::assertSent(fn (Request $request) => $request->url() === 'https://nawiri.test/api/jambopay/business/payroll/wallet'
            && str_starts_with($request->header('Idempotency-Key')[0] ?? '', 'circular-req-')
            && $request['pin'] === '1234'
            && $request['amount'] === 1500
            && $request['phoneNumber'] === '254733333333'
        );
        $this->assertCount(1, Http::recorded(fn (Request $request) => $request->url() === 'https://nawiri.test/api/jambopay/business/payroll/wallet'
        ));
    }

    public function test_reconciliation_marks_the_requisition_paid_only_after_nawiri_confirms_completion(): void
    {
        [$admin, $requisition] = $this->approvedRequisition();
        $payment = RequisitionPayment::create([
            'requisition_id' => $requisition->id,
            'initiated_by_id' => $admin->id,
            'category' => RequisitionPayment::CATEGORY_TRANSPORT,
            'amount_minor' => 150000,
            'phone_number' => '254712345678',
            'provider' => 'NAWIRI_WALLET',
            'status' => RequisitionPayment::STATUS_SUBMITTED,
            'idempotency_key' => 'circular-test-payment',
            'nawiri_payment_id' => 'c00f5619-2daa-47ad-9a4d-da4686a3cc75',
        ]);

        Http::fake([
            'https://nawiri.test/api/auth/login' => Http::response([
                'access_token' => 'access-token',
                'expires_in' => 300,
            ]),
            'https://nawiri.test/api/jambopay/business/payroll/wallet/*/reconcile' => Http::response([
                'payment' => [
                    'id' => $payment->nawiri_payment_id,
                    'status' => 'COMPLETED',
                ],
            ]),
        ]);

        $response = $this->actingAs($admin)->post(
            route('admin.requisition-payments.reconcile', $payment),
        );

        $response->assertRedirect()->assertSessionHas('status', 'Nawiri confirmed the payment.');
        $this->assertSame(RequisitionPayment::STATUS_COMPLETED, $payment->fresh()->status);
        $this->assertSame('1500.00', $requisition->fresh()->transport_paid_amount);
        Http::assertSent(fn (Request $request) => $request->url() === "https://nawiri.test/api/jambopay/business/payroll/wallet/{$payment->nawiri_payment_id}/reconcile"
            && $request->method() === 'POST'
        );
    }

    public function test_reconciliation_removes_a_completed_credit_when_nawiri_reverses_it(): void
    {
        [$admin, $requisition] = $this->approvedRequisition();
        $requisition->update(['transport_paid_amount' => 1500]);
        $payment = RequisitionPayment::create([
            'requisition_id' => $requisition->id,
            'initiated_by_id' => $admin->id,
            'category' => RequisitionPayment::CATEGORY_TRANSPORT,
            'amount_minor' => 150000,
            'phone_number' => '254712345678',
            'provider' => 'NAWIRI_WALLET',
            'status' => RequisitionPayment::STATUS_COMPLETED,
            'idempotency_key' => 'circular-reversed-payment',
            'nawiri_payment_id' => 'c00f5619-2daa-47ad-9a4d-da4686a3cc76',
            'completed_at' => now(),
        ]);

        Http::fake([
            'https://nawiri.test/api/auth/login' => Http::response([
                'access_token' => 'access-token',
                'expires_in' => 300,
            ]),
            'https://nawiri.test/api/jambopay/business/payroll/wallet/*/reconcile' => Http::response([
                'payment' => [
                    'id' => $payment->nawiri_payment_id,
                    'status' => 'REVERSED',
                    'failureReason' => 'Provider reversed the transfer.',
                ],
            ]),
        ]);

        $payment = app(RequisitionPaymentService::class)->reconcile($payment);

        $this->assertSame(RequisitionPayment::STATUS_FAILED, $payment->status);
        $this->assertSame('Provider reversed the transfer.', $payment->failure_reason);
        $this->assertSame('0.00', $requisition->fresh()->transport_paid_amount);
    }

    public function test_supervisor_cannot_move_money(): void
    {
        [, $requisition] = $this->approvedRequisition();
        $supervisor = User::factory()->create([
            'role' => User::ROLE_SUPERVISOR,
            'phone_number' => '254700000002',
        ]);

        Http::fake();

        $this->actingAs($supervisor)
            ->post(route('admin.requisitions.transport.pay', $requisition), [
            ])
            ->assertForbidden();

        $this->assertDatabaseCount('requisition_payments', 0);
        Http::assertNothingSent();
    }

    public function test_regular_admin_cannot_move_money(): void
    {
        [, $requisition] = $this->approvedRequisition();
        $admin = User::factory()->create([
            'role' => User::ROLE_ADMIN,
            'phone_number' => '254700000005',
        ]);

        Http::fake();

        $this->actingAs($admin)
            ->post(route('admin.requisitions.transport.pay', $requisition))
            ->assertForbidden();

        $this->assertDatabaseCount('requisition_payments', 0);
        Http::assertNothingSent();
    }

    public function test_regular_admin_cannot_reconcile_a_payment(): void
    {
        [$officeAdmin, $requisition] = $this->approvedRequisition();
        $admin = User::factory()->create([
            'role' => User::ROLE_ADMIN,
            'phone_number' => '254700000006',
        ]);
        $payment = RequisitionPayment::create([
            'requisition_id' => $requisition->id,
            'initiated_by_id' => $officeAdmin->id,
            'category' => RequisitionPayment::CATEGORY_TRANSPORT,
            'amount_minor' => 150000,
            'phone_number' => '254733333333',
            'provider' => 'NAWIRI_WALLET',
            'status' => RequisitionPayment::STATUS_SUBMITTED,
            'idempotency_key' => 'regular-admin-cannot-reconcile',
            'nawiri_payment_id' => '20000000-0000-4000-8000-000000000001',
        ]);

        Http::fake();

        $this->actingAs($admin)
            ->post(route('admin.requisition-payments.reconcile', $payment))
            ->assertForbidden();

        Http::assertNothingSent();
    }

    public function test_only_office_admin_sees_payment_controls(): void
    {
        [$officeAdmin, $requisition] = $this->approvedRequisition();
        $admin = User::factory()->create([
            'role' => User::ROLE_ADMIN,
            'phone_number' => '254700000007',
        ]);

        $this->actingAs($admin)
            ->get(route('admin.requisitions.index'))
            ->assertOk()
            ->assertDontSee('Pay 1 recipient', false);

        $this->actingAs($officeAdmin)
            ->get(route('admin.requisitions.index'))
            ->assertOk()
            ->assertSee('Pay 1 recipient', false)
            ->assertSee(route('admin.requisitions.transport.pay', $requisition), false);
    }

    public function test_requester_can_supply_multiple_nawiri_recipient_numbers(): void
    {
        $requester = User::factory()->create([
            'role' => User::ROLE_RM,
            'phone_number' => '254700000003',
        ]);

        $response = $this->actingAs($requester)->post(route('requisitions.store'), [
            'institutions' => ['KCA University'],
            'working_day' => now()->toDateString(),
            'recipient_phone_numbers' => ['0712345678', '0112345678'],
            'transport_amount_requested' => 1500,
            'airtime_amount_requested' => 5,
        ]);

        $response->assertRedirect(route('requisitions.mine'));

        $requisition = Requisition::query()->sole();
        $this->assertSame(['254712345678', '254112345678'], $requisition->recipient_phone_numbers);
        $this->assertSame(3000.0, $requisition->transportBalance());
        $this->assertSame(10.0, $requisition->airtimeBalance());
    }

    public function test_requester_cannot_create_a_fractional_kes_balance(): void
    {
        $requester = User::factory()->create([
            'role' => User::ROLE_RM,
            'phone_number' => '254700000004',
        ]);

        $this->actingAs($requester)->post(route('requisitions.store'), [
            'institutions' => ['KCA University'],
            'working_day' => now()->toDateString(),
            'recipient_phone_numbers' => ['0712345678'],
            'transport_amount_requested' => 10.50,
            'airtime_amount_requested' => 5,
        ])->assertSessionHasErrors('transport_amount_requested');

        $this->assertDatabaseCount('requisitions', 0);
    }

    public function test_scheduler_detects_a_recent_post_completion_reversal(): void
    {
        [$admin, $requisition] = $this->approvedRequisition();
        $requisition->update(['transport_paid_amount' => 1500]);
        $payment = RequisitionPayment::create([
            'requisition_id' => $requisition->id,
            'initiated_by_id' => $admin->id,
            'category' => RequisitionPayment::CATEGORY_TRANSPORT,
            'amount_minor' => 150000,
            'phone_number' => '254712345678',
            'provider' => 'NAWIRI_WALLET',
            'status' => RequisitionPayment::STATUS_COMPLETED,
            'idempotency_key' => 'circular-scheduled-reversal',
            'nawiri_payment_id' => 'c00f5619-2daa-47ad-9a4d-da4686a3cc77',
            'completed_at' => now()->subHour(),
        ]);
        DB::table('requisition_payments')->where('id', $payment->id)->update([
            'updated_at' => now()->subMinutes(2),
        ]);

        Http::fake([
            'https://nawiri.test/api/auth/login' => Http::response([
                'access_token' => 'access-token',
                'expires_in' => 300,
            ]),
            'https://nawiri.test/api/jambopay/business/payroll/wallet/*/reconcile' => Http::response([
                'payment' => [
                    'id' => $payment->nawiri_payment_id,
                    'status' => 'REVERSED',
                    'failureReason' => 'Provider reversed the transfer.',
                ],
            ]),
        ]);

        $this->assertSame(0, Artisan::call('payroll:reconcile'));
        $this->assertSame(RequisitionPayment::STATUS_FAILED, $payment->fresh()->status);
        $this->assertSame('0.00', $requisition->fresh()->transport_paid_amount);
    }

    public function test_office_admin_pay_action_ignores_tampered_recipient_and_amount_fields(): void
    {
        [$admin, $requisition] = $this->approvedRequisition();

        Http::fake([
            'https://nawiri.test/api/auth/login' => Http::response([
                'access_token' => 'access-token',
                'expires_in' => 300,
            ]),
            'https://nawiri.test/api/jambopay/business/payroll/wallet' => Http::response([
                'payment' => [
                    'id' => 'd00f5619-2daa-47ad-9a4d-da4686a3cc75',
                    'status' => 'COMPLETED',
                ],
            ]),
        ]);

        $this->actingAs($admin)->post(
            route('admin.requisitions.transport.pay', $requisition),
            ['paid_amount' => 1, 'recipient_phone' => '254799999999'],
        )->assertSessionHas('status');

        $this->assertDatabaseHas('requisition_payments', [
            'phone_number' => '254733333333',
            'amount_minor' => 150000,
            'status' => RequisitionPayment::STATUS_COMPLETED,
        ]);
        $this->assertDatabaseMissing('requisition_payments', ['phone_number' => '254799999999']);
    }

    public function test_pay_sends_the_per_recipient_amount_to_every_number(): void
    {
        [$admin, $requisition] = $this->approvedRequisition(['254712345678', '254733333333'], 10);
        $paymentNumber = 0;

        Http::fake(function (Request $request) use (&$paymentNumber) {
            if ($request->url() === 'https://nawiri.test/api/auth/login') {
                return Http::response(['access_token' => 'access-token', 'expires_in' => 300]);
            }

            $paymentNumber++;

            return Http::response([
                'payment' => [
                    'id' => sprintf('00000000-0000-4000-8000-%012d', $paymentNumber),
                    'status' => 'COMPLETED',
                ],
            ]);
        });

        $this->actingAs($admin)
            ->post(route('admin.requisitions.transport.pay', $requisition))
            ->assertSessionHas('status', '2 recipients paid.');

        $this->assertDatabaseHas('requisition_payments', [
            'phone_number' => '254712345678',
            'amount_minor' => 1000,
            'status' => RequisitionPayment::STATUS_COMPLETED,
        ]);
        $this->assertDatabaseHas('requisition_payments', [
            'phone_number' => '254733333333',
            'amount_minor' => 1000,
            'status' => RequisitionPayment::STATUS_COMPLETED,
        ]);
        $this->assertDatabaseCount('requisition_payments', 2);
        $this->assertSame('20.00', $requisition->fresh()->transport_paid_amount);
        $this->assertSame(0.0, $requisition->fresh()->transportBalance());
    }

    public function test_retry_after_a_partial_failure_only_pays_the_unpaid_recipient(): void
    {
        [$admin, $requisition] = $this->approvedRequisition(['254712345678', '254733333333'], 10);
        $retrying = false;

        Http::fake(function (Request $request) use (&$retrying) {
            if ($request->url() === 'https://nawiri.test/api/auth/login') {
                return Http::response(['access_token' => 'access-token', 'expires_in' => 300]);
            }

            if ($request['phoneNumber'] === '254712345678') {
                return Http::response([
                    'payment' => [
                        'id' => '10000000-0000-4000-8000-000000000001',
                        'status' => 'COMPLETED',
                    ],
                ]);
            }

            return $retrying
                ? Http::response([
                    'payment' => [
                        'id' => '10000000-0000-4000-8000-000000000002',
                        'status' => 'COMPLETED',
                    ],
                ])
                : Http::response(['message' => 'Wallet unavailable.'], 422);
        });

        $this->actingAs($admin)
            ->post(route('admin.requisitions.transport.pay', $requisition))
            ->assertSessionHas('warning');

        $this->assertSame('10.00', $requisition->fresh()->transport_paid_amount);
        $this->assertSame(10.0, $requisition->fresh()->transportBalance());

        $retrying = true;

        $this->actingAs($admin)
            ->post(route('admin.requisitions.transport.pay', $requisition))
            ->assertSessionHas('status', '1 recipient paid.');

        $this->assertSame(1, RequisitionPayment::query()->where('phone_number', '254712345678')->count());
        $this->assertSame(2, RequisitionPayment::query()->where('phone_number', '254733333333')->count());
        $this->assertSame('20.00', $requisition->fresh()->transport_paid_amount);
        $this->assertSame(0.0, $requisition->fresh()->transportBalance());
    }

    private function approvedRequisition(array $recipientPhoneNumbers = ['254733333333'], int $transportAmount = 1500): array
    {
        $admin = User::factory()->create([
            'role' => User::ROLE_OFFICE_ADMIN,
            'phone_number' => '254700000001',
        ]);
        $requester = User::factory()->create([
            'role' => User::ROLE_RM,
            'phone_number' => '254712345678',
        ]);
        $requisition = Requisition::create([
            'requester_id' => $requester->id,
            'institution_visiting' => 'Treasury',
            'working_day' => now()->toDateString(),
            'recipient_phone_numbers' => $recipientPhoneNumbers,
            'transport_requested_at' => now(),
            'transport_amount_requested' => $transportAmount,
            'transport_status' => Requisition::STATUS_APPROVED,
            'transport_approved_by_id' => $admin->id,
            'transport_approved_at' => now(),
            'airtime_requested_at' => now(),
            'airtime_amount_requested' => 250,
        ]);

        return [$admin, $requisition];
    }
}
