<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Services\Agent360SsoService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Once RMs sign in only through Agent 360 (AGENT360_SSO_ONLY), an RM's session that did not start there (signed in
 * before the switch, or kept by "remember me") is signed out and sent to Agent 360. The sign-in page refuses new ones.
 */
class EnsureRmCameThroughAgent360
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::user();

        if (! $user instanceof User || $user->role !== User::ROLE_RM || ! Agent360SsoService::only()
            || $request->session()->get(Agent360SsoService::SESSION_KEY) === $user->id) {
            return $next($request);
        }

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return $request->expectsJson()
            ? response()->json(['message' => 'RMs sign in through Agent 360: '.config('services.agent360.sso_login_url')], 401)
            : redirect()->away((string) config('services.agent360.sso_login_url'));
    }
}
