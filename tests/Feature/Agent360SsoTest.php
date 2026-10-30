<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Agent 360 single sign-in: RMs sign in only at Agent 360, which posts a signed one-time pass to /sso/agent360.
 */
class Agent360SsoTest extends TestCase
{
    use RefreshDatabase;

    private string $secret;

    private User $rm;

    protected function setUp(): void
    {
        parent::setUp();
        $pair = sodium_crypto_sign_keypair();
        $this->secret = sodium_crypto_sign_secretkey($pair);
        config(['services.agent360.sso_public_key' => base64_encode(sodium_crypto_sign_publickey($pair))]);
        $this->rm = User::factory()->create(['role' => User::ROLE_RM, 'phone_number' => '254712345678', 'email' => 'grace@example.com', 'password' => 'WicPass123', 'is_active' => true]);
    }

    private function pass(array $claims = [], ?string $secret = null): string
    {
        $b64 = fn (string $bytes) => rtrim(strtr(base64_encode($bytes), '+/', '-_'), '=');
        $body = $b64(json_encode([
            'iss' => 'agent360', 'aud' => 'WESTPORT', 'sub' => (string) $this->rm->id, 'amr' => ['pwd', 'otp'],
            'iat' => time(), 'exp' => time() + 60, 'jti' => Str::random(32), ...$claims,
        ]));

        return $body.'.'.$b64(sodium_crypto_sign_detached($body, $secret ?? $this->secret));
    }

    public function test_an_rm_from_agent_360_lands_on_their_dashboard(): void
    {
        $this->post('/sso/agent360', ['pass' => $this->pass()])->assertRedirect(route('rm.dashboard'));
        $this->assertAuthenticatedAs($this->rm);
    }

    public function test_supervisors_and_admins_from_agent_360_land_on_their_own_home_page(): void
    {
        foreach ([
            [User::ROLE_SUPERVISOR, 'admin.dashboard'],
            [User::ROLE_ADMIN, 'admin.dashboard'],
            [User::ROLE_OPERATIONS, 'admin.dashboard'],
            [User::ROLE_OFFICE_ADMIN, 'admin.requisitions.index'],
        ] as $i => [$role, $home]) {
            $user = User::factory()->create(['role' => $role, 'phone_number' => '25470000041'.$i, 'is_active' => true]);
            $this->post('/sso/agent360', ['pass' => $this->pass(['sub' => (string) $user->id])])->assertRedirect(route($home));
            $this->assertAuthenticatedAs($user);
            auth()->logout();
        }
    }

    public function test_forged_expired_reused_or_inactive_passes_are_refused(): void
    {
        $inactive = User::factory()->create(['role' => User::ROLE_SUPERVISOR, 'phone_number' => '254700000401', 'is_active' => false]);

        foreach ([
            $this->pass([], sodium_crypto_sign_secretkey(sodium_crypto_sign_keypair())),
            $this->pass(['aud' => 'AHB']),
            $this->pass(['exp' => time() - 120]),
            $this->pass(['sub' => (string) $inactive->id]),
            $this->pass(['sub' => '999999']),
            'not-a-pass',
        ] as $pass) {
            $this->post('/sso/agent360', ['pass' => $pass])->assertForbidden();
        }

        $pass = $this->pass();
        $this->post('/sso/agent360', ['pass' => $pass])->assertRedirect();
        auth()->logout();
        $this->post('/sso/agent360', ['pass' => $pass])->assertForbidden();
        $this->assertGuest();
    }

    public function test_rms_are_sent_to_agent_360_from_the_westport_sign_in_once_only_agent_360_is_on(): void
    {
        $this->get('/login')->assertSee('RM? Sign in through Agent 360');

        // Testing period (key only): RMs can still sign in here
        $this->post('/login', ['email' => 'grace@example.com', 'password' => 'WicPass123'])->assertRedirect(route('rm.dashboard'));
        auth()->logout();

        config(['services.agent360.sso_only' => true]);

        $this->from('/login')->post('/login', ['email' => 'grace@example.com', 'password' => 'WicPass123'])
            ->assertRedirect('/login')->assertSessionHasErrors('email');
        $this->assertGuest();

        $admin = User::factory()->create(['role' => User::ROLE_ADMIN, 'phone_number' => '254700000402', 'email' => 'admin@example.com', 'password' => 'AdminPass123']);
        $this->post('/login', ['email' => 'admin@example.com', 'password' => 'AdminPass123'])->assertRedirect(route('admin.dashboard'));
        $this->assertAuthenticatedAs($admin);
    }

    public function test_an_rm_signed_in_before_the_switch_is_signed_out_and_sent_to_agent_360(): void
    {
        config(['services.agent360.sso_only' => true, 'services.agent360.sso_login_url' => 'https://agent360.test/rm/login']);

        // Signed in before the switch (or kept by "remember me")
        $this->actingAs($this->rm)->get(route('rm.dashboard'))->assertRedirect('https://agent360.test/rm/login');
        $this->assertGuest();

        // Through Agent 360 they stay in
        $this->post('/sso/agent360', ['pass' => $this->pass()])->assertRedirect(route('rm.dashboard'));
        $this->get(route('rm.dashboard'))->assertOk();

        // Other staff are not affected
        auth()->logout();
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN, 'phone_number' => '254700000403', 'email' => 'admin2@example.com']);
        $this->actingAs($admin)->get(route('admin.dashboard'))->assertOk();
    }
}
