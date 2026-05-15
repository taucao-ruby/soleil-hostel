<?php

namespace Tests\Feature\Middleware;

use App\Http\Middleware\CheckHttpOnlyTokenValid;
use Illuminate\Http\Request;
use ReflectionMethod;
use Tests\TestCase;

/**
 * F-39 — Cookie header fallback parser hardening.
 *
 * Targets the private `extractCookieFromHeader` helper directly via Reflection
 * because the column types (UUID-typed `token_identifier`) prevent us from
 * exercising values like `abc=def` or `abc+def` through a full auth round-trip.
 *
 * Asserted invariants:
 * - Exact cookie-name match (no prefix collision such as `soleil_token_backup`
 *   silently being accepted as `soleil_token`).
 * - Cookie values containing `=` (URL-encoded as `%3D`) are decoded and kept whole.
 * - `+` is preserved (rawurldecode, not urldecode which would convert it to space).
 * - Missing/empty Cookie header returns null cleanly.
 */
class CheckHttpOnlyTokenValidCookieParsingTest extends TestCase
{
    private CheckHttpOnlyTokenValid $middleware;

    private ReflectionMethod $extractMethod;

    protected function setUp(): void
    {
        parent::setUp();

        $this->middleware = new CheckHttpOnlyTokenValid;
        $this->extractMethod = new ReflectionMethod(
            CheckHttpOnlyTokenValid::class,
            'extractCookieFromHeader'
        );
        $this->extractMethod->setAccessible(true);
    }

    private function extract(string $cookieHeader, string $cookieName = 'soleil_token'): ?string
    {
        $request = Request::create('/api/auth/me-httponly', 'GET');
        $request->headers->set('Cookie', $cookieHeader);

        return $this->extractMethod->invoke($this->middleware, $request, $cookieName);
    }

    public function test_extracts_exact_named_cookie_among_other_cookies(): void
    {
        $value = $this->extract('foo=bar; soleil_token=valid-token-identifier; other=value');

        $this->assertSame('valid-token-identifier', $value);
    }

    public function test_does_not_match_cookie_with_longer_name_prefix(): void
    {
        // soleil_token_backup must NEVER be accepted as soleil_token,
        // even when it carries what looks like a valid token value.
        $value = $this->extract('soleil_token_backup=should-not-be-extracted; other=value');

        $this->assertNull($value);
    }

    public function test_does_not_match_cookie_with_shorter_name(): void
    {
        // soleil must NEVER be accepted as soleil_token.
        $value = $this->extract('soleil=should-not-be-extracted');

        $this->assertNull($value);
    }

    public function test_url_encoded_equals_in_value_is_decoded_and_preserved(): void
    {
        // %3D → '='. The full value (abc=def) must survive parsing —
        // the original implementation's substr() preserved the literal '=',
        // but did not decode percent-encoded sequences. The hardened helper
        // does both correctly.
        $value = $this->extract('soleil_token=abc%3Ddef');

        $this->assertSame('abc=def', $value);
    }

    public function test_plus_sign_is_preserved_not_converted_to_space(): void
    {
        // rawurldecode preserves '+'. urldecode would convert it to ' ',
        // which would silently corrupt any future cookie payload that
        // happens to contain '+'.
        $value = $this->extract('soleil_token=abc+def');

        $this->assertSame('abc+def', $value);
    }

    public function test_handles_whitespace_around_pairs(): void
    {
        $value = $this->extract('  foo=bar ;   soleil_token=trimmed-value  ');

        $this->assertSame('trimmed-value', $value);
    }

    public function test_returns_null_when_cookie_header_missing(): void
    {
        $request = Request::create('/api/auth/me-httponly', 'GET');

        $value = $this->extractMethod->invoke($this->middleware, $request, 'soleil_token');

        $this->assertNull($value);
    }

    public function test_returns_null_when_cookie_header_empty(): void
    {
        $value = $this->extract('');

        $this->assertNull($value);
    }

    public function test_returns_null_when_no_pair_has_equals(): void
    {
        $value = $this->extract('soleil_token; other');

        $this->assertNull($value);
    }

    public function test_picks_first_match_and_stops(): void
    {
        // Defensive: even if a duplicate name appears, the first occurrence wins.
        $value = $this->extract('soleil_token=first; soleil_token=second');

        $this->assertSame('first', $value);
    }
}
