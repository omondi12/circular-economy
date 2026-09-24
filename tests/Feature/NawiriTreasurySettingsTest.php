<?php

namespace Tests\Feature;

use App\Exceptions\NawiriPayrollException;
use App\Models\NawiriTreasuryCredential;
use App\Models\Requisition;
use App\Models\RequisitionPayment;
use App\Models\User;
use App\Services\NawiriPayrollClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class NawiriTreasurySettingsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('cache.default', 'array');
        config()->set('services.nawiri_payroll', [
            'base_url' => 'https://nawiri.test',
            'timeout' => 10,
        ]);
    }

    public function test_only_an_admin_can_open_the_treasury_settings(): void
    {
        $supervisor = $this->user(User::ROLE_SUPERVISOR, '254700000011');

        $this->get(route('admin.nawiri-treasury.edit'))->assertRedirect(route('login'));
        $this->actingAs($supervisor)->get(route('admin.nawiri-treasury.edit'))->assertForbidden();
    }

    public function test_payroll_requires_a_saved_treasury_account(): void
    {
        Http::fake();
        $payment = new RequisitionPayment([
            'requisition_id' => 1,
            'category' => RequisitionPayment::CATEGORY_TRANSPORT,
            'amount_minor' => 1000,
            'phone_number' => '254712345678',
            'idempotency_key' => 'missing-treasury-test',
        ]);

        try {
            app(NawiriPayrollClient::class)->submit($payment);
            $this->fail('Payroll should refuse to run without a saved treasury account.');
        } catch (NawiriPayrollException $exception) {
            $this->assertSame(
                'A verified Nawiri treasury account is required before payroll can run.',
                $exception->getMessage(),
            );
        }

        Http::assertNothingSent();
    }

    public function test_admin_can_verify_and_store_encrypted_treasury_credentials(): void
    {
        $admin = $this->user(User::ROLE_ADMIN, '254700000012');

        Http::fake([
            'https://nawiri.test/api/auth/login' => Http::response([
                'access_token' => 'verified-token',
                'expires_in' => 300,
                'user' => ['email' => 'treasury@example.com'],
            ]),
            'https://nawiri.test/api/admin/pin/verify' => Http::response(['valid' => true]),
        ]);

        $this->actingAs($admin)->put(route('admin.nawiri-treasury.update'), [
            'email' => 'Treasury@Example.com',
            'password' => 'secure-password',
            'pin' => '1234',
        ])->assertRedirect(route('admin.nawiri-treasury.edit'))
            ->assertSessionHas('status');

        $credential = NawiriTreasuryCredential::query()->sole();
        $raw = DB::table('nawiri_treasury_credentials')->first();

        $this->assertSame('treasury@example.com', $credential->email);
        $this->assertSame('secure-password', $credential->password);
        $this->assertSame('1234', $credential->pin);
        $this->assertNotSame('secure-password', $raw->password);
        $this->assertNotSame('1234', $raw->pin);
        $this->assertNotNull($credential->verified_at);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'nawiri_treasury.updated',
            'subject_id' => $credential->id,
        ]);
        Http::assertSent(fn (Request $request) => $request->url() === 'https://nawiri.test/api/admin/pin/verify'
            && $request->hasHeader('Authorization', 'Bearer verified-token')
            && $request['pin'] === '1234');
    }

    public function test_rejected_login_does_not_replace_the_treasury_account(): void
    {
        $admin = $this->user(User::ROLE_ADMIN, '254700000013');

        Http::fake([
            'https://nawiri.test/api/auth/login' => Http::response(['error' => 'Invalid credentials'], 401),
        ]);

        $this->actingAs($admin)->put(route('admin.nawiri-treasury.update'), [
            'email' => 'wrong@example.com',
            'password' => 'wrong-password',
            'pin' => '1234',
        ])->assertSessionHasErrors('email');

        $this->assertDatabaseCount('nawiri_treasury_credentials', 0);
    }

    public function test_temporary_login_failure_is_not_reported_as_bad_credentials(): void
    {
        $admin = $this->user(User::ROLE_ADMIN, '254700000017');

        Http::fake([
            'https://nawiri.test/api/auth/login' => Http::response(['error' => 'unavailable'], 503),
        ]);

        $this->actingAs($admin)->put(route('admin.nawiri-treasury.update'), [
            'email' => 'treasury@example.com',
            'password' => 'secure-password',
            'pin' => '1234',
        ])->assertSessionHasErrors([
            'email' => 'Nawiri could not verify the account right now. The treasury account was not changed.',
        ]);

        $this->assertDatabaseCount('nawiri_treasury_credentials', 0);
    }

    public function test_rejected_pin_does_not_replace_the_treasury_account(): void
    {
        $admin = $this->user(User::ROLE_ADMIN, '254700000016');

        Http::fake([
            'https://nawiri.test/api/auth/login' => Http::response([
                'access_token' => 'verified-token',
                'expires_in' => 300,
            ]),
            'https://nawiri.test/api/admin/pin/verify' => Http::response([
                'error' => 'invalid pin',
                'code' => 'INVALID_PIN',
            ], 422),
        ]);

        $this->actingAs($admin)->put(route('admin.nawiri-treasury.update'), [
            'email' => 'treasury@example.com',
            'password' => 'secure-password',
            'pin' => '0000',
        ])->assertSessionHasErrors('pin');

        $this->assertDatabaseCount('nawiri_treasury_credentials', 0);
    }

    public function test_blank_secret_fields_keep_the_existing_password_and_pin(): void
    {
        $admin = $this->user(User::ROLE_ADMIN, '254700000014');
        NawiriTreasuryCredential::create([
            'email' => 'old@example.com',
            'password' => 'existing-password',
            'pin' => '4321',
            'verified_at' => now(),
            'updated_by_id' => $admin->id,
        ]);

        Http::fake([
            'https://nawiri.test/api/auth/login' => Http::response([
                'access_token' => 'verified-token',
                'expires_in' => 300,
            ]),
            'https://nawiri.test/api/admin/pin/verify' => Http::response(['valid' => true]),
        ]);

        $this->actingAs($admin)->put(route('admin.nawiri-treasury.update'), [
            'email' => 'new@example.com',
            'password' => '',
            'pin' => '',
        ])->assertSessionHasNoErrors();

        $credential = NawiriTreasuryCredential::query()->sole();
        $this->assertSame('new@example.com', $credential->email);
        $this->assertSame('existing-password', $credential->password);
        $this->assertSame('4321', $credential->pin);
        Http::assertSent(fn (Request $request) => $request->url() === 'https://nawiri.test/api/auth/login'
            && $request['email'] === 'new@example.com'
            && $request['password'] === 'existing-password');
    }

    public function test_payroll_uses_the_saved_treasury_account(): void
    {
        $admin = $this->user(User::ROLE_ADMIN, '254700000015');
        $officeAdmin = $this->user(User::ROLE_OFFICE_ADMIN, '254700000016');
        $requester = $this->user(User::ROLE_RM, '254712345678');
        NawiriTreasuryCredential::create([
            'email' => 'saved@example.com',
            'password' => 'saved-password',
            'pin' => '2468',
            'verified_at' => now(),
            'updated_by_id' => $admin->id,
        ]);
        $requisition = Requisition::create([
            'requester_id' => $requester->id,
            'institution_visiting' => 'Treasury',
            'working_day' => now()->toDateString(),
            'recipient_phone_numbers' => ['254712345678'],
            'transport_requested_at' => now(),
            'transport_amount_requested' => 10,
            'transport_status' => Requisition::STATUS_APPROVED,
            'transport_approved_by_id' => $admin->id,
            'transport_approved_at' => now(),
            'airtime_requested_at' => now(),
            'airtime_amount_requested' => 0,
        ]);

        Http::fake([
            'https://nawiri.test/api/auth/login' => Http::response([
                'access_token' => 'stored-account-token',
                'expires_in' => 300,
            ]),
            'https://nawiri.test/api/jambopay/business/payroll/wallet' => Http::response([
                'payment' => ['id' => 'payment-id', 'status' => 'COMPLETED'],
            ]),
        ]);

        $this->actingAs($officeAdmin)->post(route('admin.requisitions.transport.pay', $requisition), [
            'paid_amount' => 10,
            'recipient_phone' => '254712345678',
        ])->assertSessionHasNoErrors();

        Http::assertSent(fn (Request $request) => $request->url() === 'https://nawiri.test/api/auth/login'
            && $request['email'] === 'saved@example.com'
            && $request['password'] === 'saved-password');
        Http::assertSent(fn (Request $request) => $request->url() === 'https://nawiri.test/api/jambopay/business/payroll/wallet'
            && $request['pin'] === '2468');
    }

    private function user(string $role, string $phone): User
    {
        return User::factory()->create([
            'role' => $role,
            'phone_number' => $phone,
        ]);
    }
}
