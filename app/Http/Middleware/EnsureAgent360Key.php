<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Server-to-server key for the Agent 360 API (/api/agent360/v1): Agent 360 is the RM register shared with Adam AHB
 * Souq, and sends this key as "Authorization: Bearer ...". With no key configured the API does not exist (404).
 */
class EnsureAgent360Key
{
    public function handle(Request $request, Closure $next): Response
    {
        $expected = (string) config('services.agent360.key');

        abort_if($expected === '', 404);
        abort_unless(hash_equals($expected, (string) $request->bearerToken()), 401, 'Invalid Agent 360 key.');

        return $next($request);
    }
}
