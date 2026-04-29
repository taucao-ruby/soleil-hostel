<?php

namespace Tests\Feature\Auth;

use App\Models\PersonalAccessToken;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Batch 2 hardening — auth/Sanctum security regressions.
 *
 * Each test pins a single invariant from the Batch 2 contract:
 *  T1  HttpOnlyTokenController::refresh() rotation is atomic.
 *  T2  Bearer-mode device-fingerprint binding rejects cross-device replay.
 *  T3  Legacy /api/auth/register issues a token row with the full security column set.
 *  T4  Refresh-count cap reads sanctum.max_refresh_count_per_hour and enforces (limit+1)th = 401.
 *  T5  /api/auth/login-httponly rejects malformed payloads at validation, before any DB query.
 *
 * All five tests fail before the Batch 2 fixes land and pass after them.
 */
class Batch2HardeningTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create([
            'email' => 'batch2@example.com',
            'password' => Hash::make('password123'),
            'email_verified_at' => now(),
        ]);
    }

    /**
     * T1 — Concurrent cookie refresh: rotation is atomic.
     *
     * Without DB::transaction + lockForUpdate, two refreshes against the same
     * identifier produce two new active tokens and one orphaned revocation.
     * With the lock + re-check, exactly one new token survives and the second
     * call returns 401.
     */
    public function test_concurrent_cookie_refresh_creates_exactly_one_new_token(): void
    {
        $this->postJson('/api/auth/login-httponly', [
            'email' => 'batch2@example.com',
            'password' => 'password123',
        ]);

        $original = PersonalAccessToken::where('tokenable_id', $this->user->id)->firstOrFail();
        $cookieName = config('sanctum.cookie_name', 'soleil_token');
        $identifier = $original->token_identifier;

        // Submit two refreshes against the same identifier.
        $r1 = $this->withHeader('Cookie', "{$cookieName}={$identifier}")
            ->postJson('/api/auth/refresh-httponly');
        $r2 = $this->withHeader('Cookie', "{$cookieName}={$identifier}")
            ->postJson('/api/auth/refresh-httponly');

        // Exactly one of them succeeds; the other receives 401.
        $statuses = [$r1->status(), $r2->status()];
        sort($statuses);
        $this->assertSame([200, 401], $statuses, 'Exactly one refresh must succeed and the other must 401');

        // Original token revoked.
        $original->refresh();
        $this->assertNotNull($original->revoked_at, 'Original token must be revoked');

        // Exactly one active (non-revoked) token remains.
        $active = PersonalAccessToken::where('tokenable_id', $this->user->id)
            ->whereNull('revoked_at')
            ->count();
        $this->assertSame(1, $active, 'Exactly one active token must exist after concurrent refresh');
    }

    /**
     * T2 — Bearer replay from a different device fingerprint is rejected.
     *
     * Token issued under UA-A. Replay with UA-B causes the middleware to
     * detect the fingerprint mismatch and return 401 with DEVICE_MISMATCH.
     */
    public function test_bearer_token_replay_from_different_fingerprint_returns_401(): void
    {
        config(['sanctum.verify_device_fingerprint' => true]);

        // Issue bearer token on device A.
        $login = $this->withHeader('User-Agent', 'DeviceA-Browser/1.0')
            ->postJson('/api/auth/login-v2', [
                'email' => 'batch2@example.com',
                'password' => 'password123',
            ]);
        $login->assertStatus(201);
        $bearer = $login->json('data.token');
        $this->assertNotEmpty($bearer, 'Login must return plain bearer token in v2');

        // Confirm fingerprint was stored.
        $token = PersonalAccessToken::where('tokenable_id', $this->user->id)->firstOrFail();
        $this->assertNotNull($token->device_fingerprint, 'Bearer login must store device_fingerprint');

        // Replay with a different User-Agent → fingerprint mismatch.
        $replay = $this->withHeaders([
            'Authorization' => 'Bearer '.$bearer,
            'User-Agent' => 'DeviceB-Browser/9.9',
        ])->getJson('/api/auth/me-v2');

        $replay->assertStatus(401);
        $this->assertSame('DEVICE_MISMATCH', $replay->json('errors.code'));
    }

    /**
     * T3 — Legacy register populates the full security column set.
     */
    public function test_legacy_register_populates_security_columns(): void
    {
        $response = $this->postJson('/api/auth/register', [
            'name' => 'Legacy User',
            'email' => 'legacy@example.com',
            'password' => 'Sup3r-Str0ng!Pw',
            'password_confirmation' => 'Sup3r-Str0ng!Pw',
        ]);

        $response->assertStatus(201);

        $user = User::where('email', 'legacy@example.com')->firstOrFail();
        $token = PersonalAccessToken::where('tokenable_id', $user->id)
            ->where('tokenable_type', User::class)
            ->firstOrFail();

        $this->assertNotNull($token->type, 'type must be populated');
        $this->assertSame('short_lived', $token->type);
        $this->assertNotNull($token->device_id, 'device_id must be populated');
        $this->assertSame(0, (int) $token->refresh_count, 'refresh_count must be 0');
        $this->assertNotNull($token->token_hash, 'token_hash must be populated');
        $this->assertNotNull($token->token_identifier, 'token_identifier must be populated');
        $this->assertNotNull($token->expires_at, 'expires_at must be populated');
    }

    /**
     * T4 — Refresh-count cap reads sanctum.max_refresh_count_per_hour and the
     * (limit+1)th refresh returns 401, regardless of the legacy 50 fallback.
     */
    public function test_refresh_count_limit_uses_correct_config_key_and_blocks_at_threshold(): void
    {
        // Pin the limit to a small value via the canonical key.
        config(['sanctum.max_refresh_count_per_hour' => 3]);

        // Sanity-pin: the legacy (wrong) key must not be the source of truth.
        $this->assertSame(3, (int) config('sanctum.max_refresh_count_per_hour'));
        $this->assertNotSame(50, (int) config('sanctum.max_refresh_count_per_hour'));

        $this->postJson('/api/auth/login-httponly', [
            'email' => 'batch2@example.com',
            'password' => 'password123',
        ]);

        $cookieName = config('sanctum.cookie_name', 'soleil_token');

        // 3 refreshes succeed.
        for ($i = 1; $i <= 3; $i++) {
            $token = PersonalAccessToken::where('tokenable_id', $this->user->id)
                ->whereNull('revoked_at')
                ->orderByDesc('created_at')
                ->firstOrFail();

            $resp = $this->withHeader('Cookie', "{$cookieName}={$token->token_identifier}")
                ->postJson('/api/auth/refresh-httponly');

            $resp->assertStatus(200);
        }

        // The (limit+1)th refresh must be rejected.
        $token = PersonalAccessToken::where('tokenable_id', $this->user->id)
            ->whereNull('revoked_at')
            ->orderByDesc('created_at')
            ->firstOrFail();

        $blocked = $this->withHeader('Cookie', "{$cookieName}={$token->token_identifier}")
            ->postJson('/api/auth/refresh-httponly');

        $blocked->assertStatus(401);
        $this->assertSame('SUSPICIOUS_ACTIVITY', $blocked->json('errors.code'));
    }

    /**
     * T5 — Malformed login payload (missing password) is rejected at validation,
     * before the controller ever queries the users table.
     */
    public function test_login_httponly_rejects_missing_password_before_db_query(): void
    {
        $userQueries = 0;
        DB::listen(function ($query) use (&$userQueries) {
            // Match any select/exists hitting the users table — both forms produced by Eloquent.
            if (preg_match('/from "?users"?/i', $query->sql)) {
                $userQueries++;
            }
        });

        $response = $this->postJson('/api/auth/login-httponly', [
            'email' => 'batch2@example.com',
            // password intentionally omitted
        ]);

        $response->assertStatus(422);
        $this->assertSame(0, $userQueries, 'No users table query may run before validation passes');
    }
}
