<?php

namespace Tests\Feature\Security;

use Tests\TestCase;

class CspHeaderTest extends TestCase
{
    public function test_production_csp_allows_required_stripe_origins(): void
    {
        config(['app.debug' => false]);

        $response = $this->get('/');
        $directives = $this->parseCsp($response->headers->get('Content-Security-Policy', ''));

        $this->assertCspDirectiveContains($directives, 'script-src', 'https://js.stripe.com');
        $this->assertCspDirectiveContains($directives, 'frame-src', 'https://js.stripe.com');
        $this->assertCspDirectiveContains($directives, 'frame-src', 'https://hooks.stripe.com');
        $this->assertCspDirectiveContains($directives, 'connect-src', 'https://api.stripe.com');
        $this->assertCspDirectiveContains($directives, 'img-src', 'https://q.stripe.com');
    }

    public function test_development_csp_allows_required_stripe_origins(): void
    {
        config(['app.debug' => true]);

        $response = $this->get('/');
        $directives = $this->parseCsp($response->headers->get('Content-Security-Policy', ''));

        $this->assertCspDirectiveContains($directives, 'script-src', 'https://js.stripe.com');
        $this->assertCspDirectiveContains($directives, 'frame-src', 'https://js.stripe.com');
        $this->assertCspDirectiveContains($directives, 'frame-src', 'https://hooks.stripe.com');
        $this->assertCspDirectiveContains($directives, 'connect-src', 'https://api.stripe.com');
        $this->assertCspDirectiveContains($directives, 'img-src', 'https://q.stripe.com');
    }

    public function test_caddyfile_csp_allows_required_stripe_origins(): void
    {
        $caddyfile = file_get_contents(dirname(base_path()).DIRECTORY_SEPARATOR.'Caddyfile');
        $this->assertIsString($caddyfile);

        preg_match('/Content-Security-Policy\s+"([^"]+)"/', $caddyfile, $matches);
        $this->assertNotEmpty($matches[1] ?? null, 'Caddyfile CSP header should be present.');

        $directives = $this->parseCsp($matches[1]);

        $this->assertCspDirectiveContains($directives, 'script-src', 'https://js.stripe.com');
        $this->assertCspDirectiveContains($directives, 'frame-src', 'https://js.stripe.com');
        $this->assertCspDirectiveContains($directives, 'frame-src', 'https://hooks.stripe.com');
        $this->assertCspDirectiveContains($directives, 'connect-src', 'https://api.stripe.com');
        $this->assertCspDirectiveContains($directives, 'img-src', 'https://q.stripe.com');
    }

    public function test_production_csp_does_not_add_unsafe_stripe_exceptions(): void
    {
        config(['app.debug' => false]);

        $response = $this->get('/');
        $csp = $response->headers->get('Content-Security-Policy', '');

        $this->assertStringNotContainsString("'unsafe-inline'", $csp);
        $this->assertStringNotContainsString("'unsafe-eval'", $csp);
    }

    public function test_csp_header_is_preserved_on_login_booking_and_admin_routes(): void
    {
        config(['app.debug' => false]);

        foreach (['/api/auth/csrf-token', '/api/v1/bookings', '/api/v1/admin/bookings'] as $uri) {
            $response = $this->get($uri);
            $csp = $response->headers->get('Content-Security-Policy', '');

            $this->assertNotEmpty($csp, "CSP header should be present for [{$uri}].");
            $this->assertStringContainsString('https://js.stripe.com', $csp);
            $this->assertStringContainsString('https://hooks.stripe.com', $csp);
            $this->assertStringContainsString('https://api.stripe.com', $csp);
            $this->assertStringContainsString('https://q.stripe.com', $csp);
        }
    }

    /**
     * @return array<string, list<string>>
     */
    private function parseCsp(string $csp): array
    {
        $directives = [];

        foreach (explode(';', $csp) as $directive) {
            $tokens = preg_split('/\s+/', trim($directive));

            if (! is_array($tokens) || $tokens === ['']) {
                continue;
            }

            $name = array_shift($tokens);

            if ($name !== null) {
                $directives[$name] = array_values($tokens);
            }
        }

        return $directives;
    }

    /**
     * @param  array<string, list<string>>  $directives
     */
    private function assertCspDirectiveContains(array $directives, string $directive, string $source): void
    {
        $this->assertArrayHasKey($directive, $directives, "CSP directive [{$directive}] should be present.");
        $this->assertContains($source, $directives[$directive], "CSP directive [{$directive}] should allow [{$source}].");
    }
}
