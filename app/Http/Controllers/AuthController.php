<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\User;
use App\Services\Agent360SsoService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;

class AuthController extends Controller
{
    public function showLogin(): View
    {
        return view('auth.login');
    }

    public function login(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $user = User::where('email', $credentials['email'])->first();

        if ($user !== null && ! $user->is_active) {
            return back()
                ->withInput($request->only('email'))
                ->withErrors(['email' => 'This account has been deactivated. Contact an administrator.']);
        }

        // RMs sign in only through Agent 360 once that is switched on (AGENT360_SSO_ONLY)
        if ($user?->role === User::ROLE_RM && Agent360SsoService::only()) {
            return back()
                ->withInput($request->only('email'))
                ->withErrors(['email' => 'RMs sign in through Agent 360: '.config('services.agent360.sso_login_url')]);
        }

        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
            return back()
                ->withInput($request->only('email'))
                ->withErrors(['email' => 'Those credentials do not match our records.']);
        }

        $request->session()->regenerate();

        return redirect()->intended(route(Auth::user()->homeRouteName()));
    }

    /**
     * POST /sso/agent360: an RM arriving from Agent 360 with a signed one-time pass. Agent 360 already checked their
     * password and two-factor code; they land on their RM dashboard.
     */
    public function agent360(Request $request, Agent360SsoService $sso): RedirectResponse|Response
    {
        $user = $sso->userFromPass((string) $request->input('pass'));

        if ($user === null) {
            return response()->view('auth.agent360-failed', ['url' => config('services.agent360.sso_login_url')], 403);
        }

        Auth::login($user);
        $request->session()->regenerate();
        AuditLog::record('agent360.sso_login', $user, ['name' => $user->name]);

        return redirect()->route($user->homeRouteName());
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('dashboard');
    }
}
