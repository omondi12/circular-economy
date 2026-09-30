<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gates the read-only public dashboard (client/ministry/materials data -
 * genuinely sensitive operational detail, not meant for random visitors)
 * behind a shared secret link instead of a login, since the boss wants
 * to check progress without logging into the system (2026-09-30). A
 * logged-in user of any role always passes through unchanged - this only
 * affects anonymous visitors. The key travels once, as ?key=... on the
 * first visit; after that it's remembered for the browser session so
 * clicking around the dashboard doesn't require it on every link, the
 * same session-remembers-the-unlock shape as the /facilitation PIN gate
 * (see RequisitionController).
 */
class EnsurePublicDashboardKey
{
    private const SESSION_KEY = 'public_dashboard_unlocked';

    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user() !== null) {
            return $next($request);
        }

        if ($request->session()->get(self::SESSION_KEY)) {
            return $next($request);
        }

        $expected = config('services.public_dashboard.key');

        if ($expected && hash_equals($expected, (string) $request->query('key', ''))) {
            $request->session()->put(self::SESSION_KEY, true);

            return $next($request);
        }

        return redirect()->route('login');
    }
}
