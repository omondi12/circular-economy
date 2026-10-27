<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Cache;

/**
 * Agent 360 single sign-in for RMs (docs/agent360-api.md): RMs sign in only at Agent 360 (phone, password and an
 * authenticator code), whose launchpad posts a one-time pass here. The pass is ed25519-signed by Agent 360; Westport
 * holds only the public key (services.agent360.sso_public_key). Any failure returns null, with no reason given.
 */
class Agent360SsoService
{
    /** Session key marking a sign-in that came through Agent 360 (EnsureRmCameThroughAgent360) */
    public const SESSION_KEY = 'agent360.sso';

    private const CLOCK_SKEW = 30;

    /**
     * RMs can open Westport from their Agent 360 launchpad.
     */
    public static function enabled(): bool
    {
        return (string) config('services.agent360.sso_public_key') !== '';
    }

    /**
     * RMs may only sign in through Agent 360 (the sign-in page refuses them).
     */
    public static function only(): bool
    {
        return self::enabled() && (bool) config('services.agent360.sso_only');
    }

    public function userFromPass(string $pass): ?User
    {
        $key = base64_decode((string) config('services.agent360.sso_public_key'), true);

        if ($key === false || strlen($key) !== SODIUM_CRYPTO_SIGN_PUBLICKEYBYTES || substr_count($pass, '.') !== 1) {
            return null;
        }

        [$body, $signature] = explode('.', $pass);
        $signature = $this->base64url($signature);

        if ($signature === null || strlen($signature) !== SODIUM_CRYPTO_SIGN_BYTES || ! sodium_crypto_sign_verify_detached($signature, $body, $key)) {
            return null;
        }

        $claims = json_decode((string) $this->base64url($body), true);

        if (! is_array($claims)
            || ($claims['iss'] ?? null) !== 'agent360'
            || ($claims['aud'] ?? null) !== 'WESTPORT'
            || ! is_int($claims['exp'] ?? null) || time() > $claims['exp'] + self::CLOCK_SKEW
            || ! is_int($claims['iat'] ?? null) || time() < $claims['iat'] - self::CLOCK_SKEW
            || ! is_string($claims['jti'] ?? null) || strlen($claims['jti']) < 16
            || ! ctype_digit((string) ($claims['sub'] ?? ''))) {
            return null;
        }

        // Each pass works once
        if (! Cache::add('agent360-sso:'.$claims['jti'], true, now()->addMinutes(5))) {
            return null;
        }

        $user = User::query()->whereKey((int) $claims['sub'])->first();

        return $user && $user->is_active && $user->role === User::ROLE_RM ? $user : null;
    }

    private function base64url(string $part): ?string
    {
        $decoded = base64_decode(strtr($part, '-_', '+/'), true);

        return $decoded === false ? null : $decoded;
    }
}
