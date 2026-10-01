<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    use RefreshDatabase;

    /**
     * The public dashboard is gated behind a shared secret key for
     * anonymous visitors since 2026-09-30 (see EnsurePublicDashboardKey) -
     * an anonymous "/" visit redirecting to /login is the current intended
     * behavior, not a regression.
     */
    public function test_an_anonymous_visitor_is_redirected_to_login(): void
    {
        $response = $this->get('/');

        $response->assertRedirect(route('login'));
    }
}
