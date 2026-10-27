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

    public function test_forged_expired_reused_or_non_rm_passes_are_refused(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN, 'phone_number' => '254700000401']);

        foreach ([
            $this->pass([], sodium_crypto_sign_secretkey(sodium_crypto_sign_keypair())),
            $this->pass(['aud' => 'AHB']),
            $this->pass(['exp' => time() - 120]),
            $this->pass(['sub' => (string) $admin->id]),
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
}
