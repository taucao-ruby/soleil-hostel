<?php

namespace Tests\Feature\Auth;

use App\Exceptions\OtpCooldownException;
use App\Models\EmailVerificationCode;
use App\Models\User;
use App\Notifications\EmailVerificationCodeNotification;
use App\Services\EmailVerificationCodeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * AUTH-004 — OTP cooldown race-condition coverage.
 *
 * The original implementation evaluated the cooldown OUTSIDE the transaction that
 * created the new OTP record. Two concurrent /api/email/send-code calls could both
 * read "no cooldown active", both insert a new code, and both deliver an email —
 * burning two codes in parallel and enabling OTP-farming.
 *
 * The fix takes a pessimistic lock on the user's latest verification record inside
 * a single DB transaction so that cooldown evaluation and code creation are
 * inseparable. These tests exercise:
 *
 *   1. Two consecutive issue() calls within the cooldown window — only the first
 *      writes a code; the second throws OtpCooldownException. (Simulates the race;
 *      under true concurrency the lock forces the same serialization.)
 *   2. The HTTP boundary returns 429 with a Retry-After header derived from the
 *      exception's retryAfter timestamp.
 *   3. Old codes are consumed before a new code is created (so a leaked older
 *      code cannot be redeemed once a fresher one exists).
 *   4. After the cooldown window, a fresh resend succeeds normally.
 *   5. The exception's retry_after_seconds is positive and bounded by the
 *      configured cooldown window.
 */
class OtpCooldownRaceTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function concurrent_resends_within_cooldown_only_one_succeeds(): void
    {
        Notification::fake();

        $user = User::factory()->unverified()->create();
        $service = app(EmailVerificationCodeService::class);

        // First issue succeeds (no prior code → no cooldown).
        $service->issue($user);

        // A second issue invoked immediately afterwards represents the racing
        // request that lost the lock contest in production. It must observe the
        // freshly-inserted code and refuse with OtpCooldownException.
        $caught = null;
        try {
            $service->issue($user);
        } catch (OtpCooldownException $e) {
            $caught = $e;
        }

        $this->assertNotNull(
            $caught,
            'Second issue() within the cooldown window must throw OtpCooldownException.'
        );

        // Exactly one un-consumed code exists for the user.
        $activeCount = EmailVerificationCode::where('user_id', $user->id)
            ->active()
            ->count();
        $this->assertSame(1, $activeCount, 'Exactly one active code must exist after the race.');

        // Exactly one notification was dispatched — the loser did not deliver an email.
        Notification::assertSentToTimes($user, EmailVerificationCodeNotification::class, 1);
    }

    /** @test */
    public function http_resend_during_cooldown_returns_429_with_retry_after_header(): void
    {
        Notification::fake();

        $user = User::factory()->unverified()->create();
        $token = $user->createToken('test-token')->plainTextToken;

        // First request issues a code.
        $this->withHeaders(['Authorization' => "Bearer {$token}"])
            ->postJson('/api/email/send-code')
            ->assertStatus(200);

        // Second request inside the cooldown must surface 429 + Retry-After.
        $response = $this->withHeaders(['Authorization' => "Bearer {$token}"])
            ->postJson('/api/email/send-code');

        $response->assertStatus(429);

        $retryAfterHeader = $response->headers->get('Retry-After');
        $this->assertNotNull($retryAfterHeader, 'Retry-After header must be present on 429.');
        $this->assertMatchesRegularExpression(
            '/^\d+$/',
            $retryAfterHeader,
            'Retry-After must be an integer seconds value.'
        );

        $retryAfter = (int) $retryAfterHeader;
        $this->assertGreaterThan(0, $retryAfter);
        $this->assertLessThanOrEqual(
            (int) config('auth.otp_cooldown_seconds', EmailVerificationCodeService::COOLDOWN_SECONDS),
            $retryAfter,
            'Retry-After must not exceed the configured cooldown window.'
        );

        // Body still includes the legacy cooldown_remaining_seconds field for clients
        // that consume it directly.
        $response->assertJsonStructure(['cooldown_remaining_seconds']);
        $this->assertSame($retryAfter, $response->json('cooldown_remaining_seconds'));
    }

    /** @test */
    public function old_codes_are_consumed_before_new_code_is_created(): void
    {
        Notification::fake();

        $user = User::factory()->unverified()->create();
        $service = app(EmailVerificationCodeService::class);

        $service->issue($user);
        $firstCodeId = EmailVerificationCode::where('user_id', $user->id)
            ->orderBy('id')
            ->value('id');

        // Travel past the cooldown window.
        $this->travel(EmailVerificationCodeService::COOLDOWN_SECONDS + 1)->seconds();

        $service->issue($user);

        $codes = EmailVerificationCode::where('user_id', $user->id)
            ->orderBy('id')
            ->get();

        $this->assertCount(2, $codes);
        $this->assertSame($firstCodeId, $codes[0]->id);
        $this->assertNotNull(
            $codes[0]->consumed_at,
            'The previous code must be consumed before the new one is created.'
        );
        $this->assertNull(
            $codes[1]->consumed_at,
            'The freshly issued code must remain active.'
        );
    }

    /** @test */
    public function single_resend_after_cooldown_succeeds_normally(): void
    {
        Notification::fake();

        $user = User::factory()->unverified()->create();
        $token = $user->createToken('test-token')->plainTextToken;

        $this->withHeaders(['Authorization' => "Bearer {$token}"])
            ->postJson('/api/email/send-code')
            ->assertStatus(200);

        $this->travel(EmailVerificationCodeService::COOLDOWN_SECONDS + 1)->seconds();

        $response = $this->withHeaders(['Authorization' => "Bearer {$token}"])
            ->postJson('/api/email/send-code');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Verification code sent to your email.',
            ]);

        $this->assertSame(
            2,
            EmailVerificationCode::where('user_id', $user->id)->count(),
            'Both codes must remain in the table — only one should still be active.',
        );
        $this->assertSame(
            1,
            EmailVerificationCode::where('user_id', $user->id)->active()->count(),
        );

        Notification::assertSentToTimes($user, EmailVerificationCodeNotification::class, 2);
    }

    /** @test */
    public function otp_cooldown_exception_carries_correct_retry_after(): void
    {
        Notification::fake();

        $user = User::factory()->unverified()->create();
        $service = app(EmailVerificationCodeService::class);

        $service->issue($user);

        try {
            $service->issue($user);
            $this->fail('Expected OtpCooldownException on immediate resend.');
        } catch (OtpCooldownException $e) {
            $cooldown = (int) config(
                'auth.otp_cooldown_seconds',
                EmailVerificationCodeService::COOLDOWN_SECONDS,
            );

            $this->assertTrue(
                $e->retryAfter->isFuture(),
                'retryAfter must point to a future timestamp.',
            );
            $this->assertGreaterThan(0, $e->retryAfterSeconds());
            $this->assertLessThanOrEqual(
                $cooldown,
                $e->retryAfterSeconds(),
                'retryAfterSeconds must be bounded by the configured cooldown window.',
            );
            $this->assertSame(OtpCooldownException::ERROR_CODE, $e->getErrorCode());
        }
    }
}
