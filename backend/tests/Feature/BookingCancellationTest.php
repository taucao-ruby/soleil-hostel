<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\BookingStatus;
use App\Enums\UserRole;
use App\Events\BookingCancelled;
use App\Exceptions\RefundFailedException;
use App\Jobs\ProcessDepositRefund;
use App\Models\Booking;
use App\Models\Room;
use App\Models\User;
use App\Services\CancellationService;
use App\Services\StripeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Notification;
use Mockery\MockInterface;
use Tests\TestCase;

class BookingCancellationTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private User $admin;

    private Room $room;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->admin = User::factory()->admin()->create();
        $this->room = Room::factory()->create();
    }

    // ===== SUCCESS CASES =====

    public function test_user_can_cancel_their_own_booking(): void
    {
        Event::fake([BookingCancelled::class]);

        $booking = Booking::factory()
            ->for($this->user)
            ->for($this->room)
            ->confirmed()
            ->create([
                'check_in' => now()->addDays(10),
                'check_out' => now()->addDays(12),
            ]);

        $response = $this->actingAs($this->user)
            ->postJson("/api/bookings/{$booking->id}/cancel");

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', BookingStatus::CANCELLED->value);

        $this->assertDatabaseHas('bookings', [
            'id' => $booking->id,
            'status' => BookingStatus::CANCELLED->value,
            'cancelled_by' => $this->user->id,
        ]);

        Event::assertDispatched(BookingCancelled::class);
    }

    public function test_admin_can_cancel_any_booking(): void
    {
        $booking = Booking::factory()
            ->for($this->user)
            ->for($this->room)
            ->confirmed()
            ->create([
                'check_in' => now()->addDays(10),
                'check_out' => now()->addDays(12),
            ]);

        $response = $this->actingAs($this->admin)
            ->postJson("/api/bookings/{$booking->id}/cancel");

        $response->assertOk()
            ->assertJsonPath('data.status', BookingStatus::CANCELLED->value);

        $this->assertDatabaseHas('bookings', [
            'id' => $booking->id,
            'cancelled_by' => $this->admin->id,
        ]);
    }

    /**
     * BL-6: terminal-state idempotent no-op contract.
     *
     * An owner retrying cancellation on an already-cancelled booking must:
     *   - succeed (HTTP 200) and not be turned into an error
     *   - not dispatch a second BookingCancelled event (cache invalidation,
     *     stay propagation, and email listener would all re-fire otherwise)
     *   - not push a duplicate ProcessDepositRefund job (would attempt a
     *     second Stripe refund call)
     *   - not send a duplicate user notification
     *   - not overwrite cancellation audit columns (cancelled_at/by, refund_*)
     *
     * This guards the separation of concerns documented on
     * BookingPolicy::cancel and CancellationService::cancel: the policy
     * authorizes the retry; the service short-circuits on terminal state.
     */
    public function test_cancelling_already_cancelled_booking_is_idempotent_noop(): void
    {
        Event::fake([BookingCancelled::class]);
        Bus::fake([ProcessDepositRefund::class]);
        Notification::fake();

        $booking = Booking::factory()
            ->for($this->user)
            ->for($this->room)
            ->create([
                'status' => BookingStatus::CANCELLED,
                'cancelled_at' => now()->subHour(),
                'cancelled_by' => $this->user->id,
                'refund_id' => 'rf_original',
                'refund_status' => 'succeeded',
                'refund_amount' => 5000,
                'payment_intent_id' => 'pi_original',
            ]);

        $original = DB::table('bookings')->where('id', $booking->id)->first();
        $this->assertNotNull($original);

        $response = $this->actingAs($this->user)
            ->postJson("/api/bookings/{$booking->id}/cancel");

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', BookingStatus::CANCELLED->value);

        // No duplicate side effects.
        Event::assertNotDispatched(BookingCancelled::class);
        Bus::assertNotDispatched(ProcessDepositRefund::class);
        Notification::assertNothingSent();

        // Audit columns and refund metadata are byte-for-byte preserved;
        // the no-op must not stamp a new cancelled_at or rewrite refund_id.
        $after = DB::table('bookings')->where('id', $booking->id)->first();
        $this->assertNotNull($after);
        $this->assertSame($original->status, $after->status);
        $this->assertSame($original->cancelled_at, $after->cancelled_at);
        $this->assertSame($original->cancelled_by, $after->cancelled_by);
        $this->assertSame($original->refund_id, $after->refund_id);
        $this->assertSame($original->refund_status, $after->refund_status);
        $this->assertSame($original->refund_amount, $after->refund_amount);
    }

    /**
     * BL-6 policy contract: BookingPolicy::cancel returns true for the
     * owner even when the booking is already cancelled. The terminal no-op
     * is enforced one layer down in CancellationService; the policy must
     * not be "fixed" into denying the retry.
     */
    public function test_owner_can_request_cancel_on_already_cancelled_booking_for_idempotency(): void
    {
        $booking = Booking::factory()
            ->for($this->user)
            ->for($this->room)
            ->create([
                'status' => BookingStatus::CANCELLED,
                'cancelled_at' => now()->subHour(),
                'cancelled_by' => $this->user->id,
            ]);

        $this->assertTrue(
            Gate::forUser($this->user)->allows('cancel', $booking),
            'Owner must be authorized to retry cancel on an already-cancelled booking (BL-6 idempotency).',
        );

        // Admin parity: matches the existing permission matrix.
        $this->assertTrue(
            Gate::forUser($this->admin)->allows('cancel', $booking),
            'Admin must remain authorized on already-cancelled bookings.',
        );
    }

    /**
     * BL-6 policy contract (counterpart): non-owner / non-admin actors must
     * NOT gain authorization just because the booking is already in a
     * terminal state. The idempotency branch sits AFTER the ownership gate
     * for a reason — protects against enumeration / unauthorized state read
     * via the cancel endpoint.
     */
    public function test_non_owner_cannot_cancel_already_cancelled_booking(): void
    {
        $intruder = User::factory()->create();

        $booking = Booking::factory()
            ->for($this->user)
            ->for($this->room)
            ->create([
                'status' => BookingStatus::CANCELLED,
                'cancelled_at' => now()->subHour(),
                'cancelled_by' => $this->user->id,
            ]);

        $this->assertFalse(
            Gate::forUser($intruder)->allows('cancel', $booking),
            'Non-owner must not be authorized to cancel an already-cancelled booking.',
        );

        // HTTP parity: the route must respond 403 — not 200 with a leaked
        // cancelled-booking payload, and not a different status for
        // already-cancelled vs active bookings (enumeration safety).
        $response = $this->actingAs($intruder)
            ->postJson("/api/bookings/{$booking->id}/cancel");

        $response->assertForbidden();
    }

    public function test_pending_booking_can_be_cancelled(): void
    {
        $booking = Booking::factory()
            ->for($this->user)
            ->for($this->room)
            ->pending()
            ->create([
                'check_in' => now()->addDays(5),
                'check_out' => now()->addDays(7),
            ]);

        $response = $this->actingAs($this->user)
            ->postJson("/api/bookings/{$booking->id}/cancel");

        $response->assertOk();
        $this->assertDatabaseHas('bookings', [
            'id' => $booking->id,
            'status' => BookingStatus::CANCELLED->value,
        ]);
    }

    // ===== REFUND CALCULATION TESTS =====

    public function test_full_refund_when_cancelled_more_than_48_hours_before_checkin(): void
    {
        // Freeze time to make test deterministic
        // At noon today, check-in 3 days later at midnight = 60 hours > 48 hours (full refund)
        $this->travelTo(now()->startOfDay()->addHours(12)); // Today at noon

        $booking = Booking::factory()
            ->for($this->user)
            ->for($this->room)
            ->confirmed()
            ->create([
                'check_in' => now()->addDays(3)->startOfDay(), // 3 days from noon = 60 hours to midnight
                'check_out' => now()->addDays(5)->startOfDay(),
                'amount' => 10000, // $100.00
                'payment_intent_id' => 'pi_test_123',
            ]);

        $refundAmount = $booking->calculateRefundAmount();

        $this->assertEquals(10000, $refundAmount); // Full refund
        $this->assertEquals(100, $booking->getRefundPercentage());
    }

    public function test_partial_refund_when_cancelled_between_24_and_48_hours_before_checkin(): void
    {
        // Freeze time at 12:00 so check_in date (midnight) is exactly 36 hours away
        // check_in is cast as 'date' (midnight), so we set current time to noon
        // making the diff to check_in (day after tomorrow's midnight) = 36 hours
        $this->travelTo(now()->startOfDay()->addHours(12)); // Today at noon

        $booking = Booking::factory()
            ->for($this->user)
            ->for($this->room)
            ->confirmed()
            ->create([
                'check_in' => now()->addDays(2)->startOfDay(), // Day after tomorrow at midnight = 36 hours from noon today
                'check_out' => now()->addDays(4)->startOfDay(),
                'amount' => 10000,
                'payment_intent_id' => 'pi_test_123',
            ]);

        $refundAmount = $booking->calculateRefundAmount();

        $this->assertEquals(5000, $refundAmount); // 50% refund
        $this->assertEquals(50, $booking->getRefundPercentage());
    }

    public function test_no_refund_when_cancelled_less_than_24_hours_before_checkin(): void
    {
        // Freeze time to make test deterministic
        // At noon today, check-in tomorrow at midnight = 12 hours < 24 hours (no refund)
        $this->travelTo(now()->startOfDay()->addHours(12)); // Today at noon

        $booking = Booking::factory()
            ->for($this->user)
            ->for($this->room)
            ->confirmed()
            ->create([
                'check_in' => now()->addDay()->startOfDay(), // Tomorrow midnight = 12 hours from noon
                'check_out' => now()->addDays(3)->startOfDay(),
                'amount' => 10000,
                'payment_intent_id' => 'pi_test_123',
            ]);

        $refundAmount = $booking->calculateRefundAmount();

        $this->assertEquals(0, $refundAmount);
        $this->assertEquals(0, $booking->getRefundPercentage());
    }

    // ===== AUTHORIZATION TESTS =====

    public function test_unauthorized_user_cannot_cancel_others_booking(): void
    {
        $otherUser = User::factory()->create();

        $booking = Booking::factory()
            ->for($this->user)
            ->for($this->room)
            ->confirmed()
            ->create();

        $response = $this->actingAs($otherUser)
            ->postJson("/api/bookings/{$booking->id}/cancel");

        $response->assertForbidden();

        $this->assertDatabaseHas('bookings', [
            'id' => $booking->id,
            'status' => BookingStatus::CONFIRMED->value,
        ]);
    }

    public function test_unauthenticated_user_cannot_cancel_booking(): void
    {
        $booking = Booking::factory()
            ->for($this->user)
            ->for($this->room)
            ->confirmed()
            ->create();

        $response = $this->postJson("/api/bookings/{$booking->id}/cancel");

        $response->assertUnauthorized();
    }

    // ===== SERVICE-LAYER DEFENSE-IN-DEPTH TESTS (Lane 3 Batch 3.1) =====

    /**
     * Direct call to CancellationService::cancel with a non-owner non-admin
     * actor must be rejected, even though no controller-level Gate ran.
     *
     * This proves the defense-in-depth ownership gate added to
     * validateCancellation(). Threat model: any code path that reaches the
     * service without going through BookingPolicy::cancel — including the
     * AI proposal confirmation flow — must not be able to cancel a booking
     * belonging to another user.
     */
    public function test_cancellation_service_rejects_non_owner_non_admin_actor(): void
    {
        $owner = $this->user;
        $intruder = User::factory()->create();

        $booking = Booking::factory()
            ->for($owner)
            ->for($this->room)
            ->confirmed()
            ->create([
                'check_in' => now()->addDays(10),
                'check_out' => now()->addDays(12),
            ]);

        $service = app(\App\Services\CancellationService::class);

        $thrown = null;
        try {
            $service->cancel($booking->fresh(), $intruder);
        } catch (\App\Exceptions\BookingCancellationException $e) {
            $thrown = $e;
        }

        $this->assertNotNull(
            $thrown,
            'CancellationService::cancel must reject non-owner non-admin actor'
        );
        $this->assertSame('unauthorized', $thrown->getErrorCode());
        $this->assertSame(403, $thrown->getHttpStatusCode());

        $this->assertDatabaseHas('bookings', [
            'id' => $booking->id,
            'status' => BookingStatus::CONFIRMED->value,
            'cancelled_by' => null,
            'cancelled_at' => null,
        ]);
    }

    /**
     * Admin actor must still be able to cancel any booking via the service
     * directly, mirroring BookingPolicy::cancel which exempts admins from
     * the ownership requirement.
     */
    public function test_cancellation_service_allows_admin_to_cancel_any_booking(): void
    {
        $booking = Booking::factory()
            ->for($this->user)
            ->for($this->room)
            ->confirmed()
            ->create([
                'check_in' => now()->addDays(10),
                'check_out' => now()->addDays(12),
            ]);

        $service = app(\App\Services\CancellationService::class);

        $cancelled = $service->cancel($booking->fresh(), $this->admin);

        $this->assertSame(BookingStatus::CANCELLED, $cancelled->status);
        $this->assertSame($this->admin->id, $cancelled->cancelled_by);
    }

    // ===== MODERATOR AUTHORIZATION TESTS =====

    public function test_moderator_can_cancel_own_booking(): void
    {
        $moderator = User::factory()->create(['role' => UserRole::MODERATOR]);

        $booking = Booking::factory()
            ->for($moderator)
            ->for($this->room)
            ->confirmed()
            ->create([
                'check_in' => now()->addDays(10),
                'check_out' => now()->addDays(12),
            ]);

        $response = $this->actingAs($moderator)
            ->postJson("/api/v1/bookings/{$booking->id}/cancel");

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', BookingStatus::CANCELLED->value);
    }

    public function test_moderator_cannot_cancel_others_booking(): void
    {
        $moderator = User::factory()->create(['role' => UserRole::MODERATOR]);

        $booking = Booking::factory()
            ->for($this->user)
            ->for($this->room)
            ->confirmed()
            ->create([
                'check_in' => now()->addDays(10),
                'check_out' => now()->addDays(12),
            ]);

        $response = $this->actingAs($moderator)
            ->postJson("/api/v1/bookings/{$booking->id}/cancel");

        $response->assertForbidden();

        $this->assertDatabaseHas('bookings', [
            'id' => $booking->id,
            'status' => BookingStatus::CONFIRMED->value,
        ]);
    }

    // ===== CANONICAL V1 PATH TESTS =====

    public function test_v1_user_can_cancel_own_booking(): void
    {
        $booking = Booking::factory()
            ->for($this->user)
            ->for($this->room)
            ->confirmed()
            ->create([
                'check_in' => now()->addDays(10),
                'check_out' => now()->addDays(12),
            ]);

        $response = $this->actingAs($this->user)
            ->postJson("/api/v1/bookings/{$booking->id}/cancel");

        $response->assertOk()
            ->assertJsonPath('data.status', BookingStatus::CANCELLED->value);
    }

    public function test_v1_admin_can_cancel_any_booking(): void
    {
        $booking = Booking::factory()
            ->for($this->user)
            ->for($this->room)
            ->confirmed()
            ->create([
                'check_in' => now()->addDays(10),
                'check_out' => now()->addDays(12),
            ]);

        $response = $this->actingAs($this->admin)
            ->postJson("/api/v1/bookings/{$booking->id}/cancel");

        $response->assertOk()
            ->assertJsonPath('data.status', BookingStatus::CANCELLED->value);
    }

    public function test_v1_unauthenticated_cannot_cancel(): void
    {
        $booking = Booking::factory()
            ->for($this->user)
            ->for($this->room)
            ->confirmed()
            ->create();

        $response = $this->postJson("/api/v1/bookings/{$booking->id}/cancel");

        $response->assertUnauthorized();
    }

    // ===== EDGE CASES =====

    public function test_cannot_cancel_after_checkin_started(): void
    {
        $booking = Booking::factory()
            ->for($this->user)
            ->for($this->room)
            ->confirmed()
            ->create([
                'check_in' => now()->subDay(), // Yesterday
                'check_out' => now()->addDays(2),
            ]);

        $response = $this->actingAs($this->user)
            ->postJson("/api/bookings/{$booking->id}/cancel");

        $response->assertForbidden();
    }

    public function test_admin_can_cancel_after_checkin_started(): void
    {
        $booking = Booking::factory()
            ->for($this->user)
            ->for($this->room)
            ->confirmed()
            ->create([
                'check_in' => now()->subDay(),
                'check_out' => now()->addDays(2),
            ]);

        $response = $this->actingAs($this->admin)
            ->postJson("/api/bookings/{$booking->id}/cancel");

        $response->assertOk();
    }

    public function test_cannot_cancel_refund_pending_booking(): void
    {
        $booking = Booking::factory()
            ->for($this->user)
            ->for($this->room)
            ->create([
                'status' => BookingStatus::REFUND_PENDING,
                'check_in' => now()->addDays(5),
            ]);

        $response = $this->actingAs($this->user)
            ->postJson("/api/bookings/{$booking->id}/cancel");

        $response->assertForbidden();
    }

    public function test_can_retry_refund_failed_booking(): void
    {
        $booking = Booking::factory()
            ->for($this->user)
            ->for($this->room)
            ->create([
                'status' => BookingStatus::REFUND_FAILED,
                'check_in' => now()->addDays(5),
                'check_out' => now()->addDays(7),
                'refund_error' => 'Previous attempt failed',
            ]);

        $response = $this->actingAs($this->user)
            ->postJson("/api/bookings/{$booking->id}/cancel");

        // Should be allowed (refund_failed is retryable)
        $response->assertStatus(200)
            ->assertJsonPath('data.status', BookingStatus::CANCELLED->value);
    }

    public function test_soft_deleted_booking_returns_404(): void
    {
        $booking = Booking::factory()
            ->for($this->user)
            ->for($this->room)
            ->confirmed()
            ->create();

        $booking->delete(); // Soft delete

        $response = $this->actingAs($this->user)
            ->postJson("/api/bookings/{$booking->id}/cancel");

        $response->assertNotFound();
    }

    // ===== STATE TRANSITION TESTS =====

    public function test_booking_without_payment_skips_refund(): void
    {
        Event::fake([BookingCancelled::class]);

        $booking = Booking::factory()
            ->for($this->user)
            ->for($this->room)
            ->confirmed()
            ->create([
                'check_in' => now()->addDays(5),
                'payment_intent_id' => null, // No payment
                'amount' => null,
            ]);

        $response = $this->actingAs($this->user)
            ->postJson("/api/bookings/{$booking->id}/cancel");

        $response->assertOk()
            ->assertJsonPath('data.status', BookingStatus::CANCELLED->value);

        $this->assertDatabaseHas('bookings', [
            'id' => $booking->id,
            'status' => BookingStatus::CANCELLED->value,
            'refund_id' => null,
            'refund_status' => null,
        ]);

        Event::assertDispatched(BookingCancelled::class);
    }

    public function test_refund_with_user_keeps_cashier_refund_path(): void
    {
        Event::fake([BookingCancelled::class]);
        config()->set('cashier.secret', 'sk_test_local');
        $this->travelTo(now()->startOfDay()->addHours(12));

        $capture = $this->fakeStripeRefundClient('re_user_refund');

        $booking = Booking::factory()
            ->for($this->user)
            ->for($this->room)
            ->confirmed()
            ->create([
                'check_in' => now()->addDays(3)->startOfDay(),
                'check_out' => now()->addDays(5)->startOfDay(),
                'amount' => 10_000,
                'payment_intent_id' => 'pi_user_refund',
            ]);

        $cancelled = app(CancellationService::class)->cancel($booking->fresh(), $this->user);

        $this->assertSame(BookingStatus::CANCELLED, $cancelled->status);
        $this->assertSame('re_user_refund', $cancelled->refund_id);
        $this->assertSame(1, $capture->calls);
        $this->assertSame([
            'payment_intent' => 'pi_user_refund',
            'amount' => 10_000,
        ], $capture->payload);
        $this->assertSame([], $capture->options);
    }

    public function test_refund_with_null_user_uses_stripe_payment_intent_fallback(): void
    {
        Event::fake([BookingCancelled::class]);
        config()->set('cashier.secret', 'sk_test_local');
        $this->travelTo(now()->startOfDay()->addHours(12));

        $capture = $this->fakeStripeRefundClient('re_orphan_refund');

        $booking = Booking::factory()
            ->for($this->room)
            ->confirmed()
            ->create([
                'user_id' => null,
                'guest_email' => 'orphan-refund@example.test',
                'check_in' => now()->addDays(2)->startOfDay(),
                'check_out' => now()->addDays(4)->startOfDay(),
                'amount' => 10_000,
                'payment_intent_id' => 'pi_orphan_refund',
            ]);

        $cancelled = app(CancellationService::class)->cancel($booking->fresh(), $this->admin);

        $this->assertSame(BookingStatus::CANCELLED, $cancelled->status);
        $this->assertSame('re_orphan_refund', $cancelled->refund_id);
        $this->assertSame(1, $capture->calls);
        $this->assertSame('pi_orphan_refund', $capture->payload['payment_intent']);
        $this->assertSame(5_000, $capture->payload['amount']);
        $this->assertSame((string) $booking->id, $capture->payload['metadata']['booking_id']);
        $this->assertSame('booking_cancellation_refund', $capture->payload['metadata']['kind']);
        $this->assertSame('cancellation_service', $capture->payload['metadata']['source']);

        $idempotencyKey = $capture->options['idempotency_key'];
        $this->assertSame("booking:{$booking->id}:refund:pi_orphan_refund", $idempotencyKey);
        $this->assertStringNotContainsString('orphan-refund@example.test', $idempotencyKey);
        $this->assertStringNotContainsString($this->admin->email, $idempotencyKey);
    }

    public function test_refund_with_null_user_and_missing_payment_intent_fails_with_domain_error(): void
    {
        $this->travelTo(now()->startOfDay()->addHours(12));

        $this->mock(StripeService::class, function (MockInterface $mock): void {
            $mock->shouldNotReceive('createBookingRefund');
        });

        $booking = Booking::factory()
            ->for($this->room)
            ->create([
                'user_id' => null,
                'status' => BookingStatus::REFUND_PENDING,
                'check_in' => now()->addDays(3)->startOfDay(),
                'check_out' => now()->addDays(5)->startOfDay(),
                'amount' => 10_000,
                'payment_intent_id' => null,
            ]);

        $service = app(CancellationService::class);
        $reflection = new \ReflectionMethod($service, 'processRefund');
        $reflection->setAccessible(true);

        $thrown = null;
        try {
            $reflection->invoke($service, $booking->fresh(), $this->admin);
        } catch (\Throwable $e) {
            $thrown = $e;
        }

        $this->assertInstanceOf(RefundFailedException::class, $thrown);
        $this->assertSame('refund_failed', $thrown->getErrorCode());

        $booking->refresh();
        $this->assertSame(BookingStatus::REFUND_FAILED, $booking->status);
        $this->assertSame('failed', $booking->refund_status);
        $this->assertStringContainsString('no Stripe PaymentIntent', (string) $booking->refund_error);
    }

    // ===== AUDIT TRAIL TESTS =====

    public function test_cancellation_records_timestamp_and_actor(): void
    {
        $booking = Booking::factory()
            ->for($this->user)
            ->for($this->room)
            ->confirmed()
            ->create([
                'check_in' => now()->addDays(5),
            ]);

        $this->freezeTime();

        $response = $this->actingAs($this->admin)
            ->postJson("/api/bookings/{$booking->id}/cancel");

        $response->assertOk();

        $this->assertDatabaseHas('bookings', [
            'id' => $booking->id,
            'cancelled_at' => now()->toDateTimeString(),
            'cancelled_by' => $this->admin->id,
        ]);
    }

    // ===== STATUS HELPER TESTS =====

    public function test_booking_status_enum_helpers(): void
    {
        $this->assertTrue(BookingStatus::PENDING->isCancellable());
        $this->assertTrue(BookingStatus::CONFIRMED->isCancellable());
        $this->assertTrue(BookingStatus::REFUND_FAILED->isCancellable());
        $this->assertFalse(BookingStatus::CANCELLED->isCancellable());
        $this->assertFalse(BookingStatus::REFUND_PENDING->isCancellable());

        $this->assertTrue(BookingStatus::CANCELLED->isTerminal());
        $this->assertFalse(BookingStatus::CONFIRMED->isTerminal());

        $this->assertTrue(BookingStatus::REFUND_PENDING->isRefundInProgress());
        $this->assertFalse(BookingStatus::CANCELLED->isRefundInProgress());
    }

    public function test_booking_status_transitions(): void
    {
        // Valid transitions
        $this->assertTrue(BookingStatus::PENDING->canTransitionTo(BookingStatus::CONFIRMED));
        $this->assertTrue(BookingStatus::PENDING->canTransitionTo(BookingStatus::CANCELLED));
        $this->assertTrue(BookingStatus::CONFIRMED->canTransitionTo(BookingStatus::REFUND_PENDING));
        $this->assertTrue(BookingStatus::REFUND_PENDING->canTransitionTo(BookingStatus::CANCELLED));
        $this->assertTrue(BookingStatus::REFUND_PENDING->canTransitionTo(BookingStatus::REFUND_FAILED));
        $this->assertTrue(BookingStatus::REFUND_FAILED->canTransitionTo(BookingStatus::CANCELLED));

        // Invalid transitions
        $this->assertFalse(BookingStatus::CANCELLED->canTransitionTo(BookingStatus::CONFIRMED));
        $this->assertFalse(BookingStatus::CANCELLED->canTransitionTo(BookingStatus::PENDING));
    }

    // ===== F-33 RACE / STALE-MODEL REGRESSION =====

    /**
     * Regression for F-33: finalizeCancellation() must re-acquire the row
     * lock and re-read state before mutating, so a stale Booking instance
     * passed by the caller (e.g. after the Stripe round-trip in
     * processRefund) cannot overwrite a newer authoritative state written by
     * a concurrent path.
     *
     * Scenario simulated:
     *   1. A booking sits in REFUND_PENDING (Phase 1 completed).
     *   2. The caller is holding a stale in-memory instance (Phase 2 in
     *      flight — Stripe just returned).
     *   3. Another path (here: a direct DB write standing in for an admin
     *      force-cancel or a webhook retry) terminates the row to CANCELLED
     *      and writes its own audit columns.
     *   4. finalizeCancellation() is invoked with the stale instance.
     *
     * Invariant verified:
     *   - The freshly locked row is the source of truth.
     *   - The stale refund_id / refund_status fields are NOT persisted on
     *     top of the racing path's audit columns.
     *   - The pre-existing audit marker (refund_error written by the racing
     *     path) survives untouched.
     *   - No additional BookingCancelled event is dispatched for the
     *     already-terminated row.
     */
    public function test_finalize_cancellation_does_not_overwrite_concurrently_cancelled_row(): void
    {
        Event::fake([BookingCancelled::class]);

        $racingActor = User::factory()->admin()->create([
            'name' => 'Race Admin',
            'email' => 'race.admin@example.test',
        ]);

        $booking = Booking::factory()
            ->for($this->user)
            ->for($this->room)
            ->create([
                'status' => BookingStatus::REFUND_PENDING,
                'check_in' => now()->addDays(5),
                'check_out' => now()->addDays(7),
                'payment_intent_id' => 'pi_f33_race',
                'amount' => 10000,
            ]);

        // Caller is holding a stale model whose in-memory status is
        // REFUND_PENDING (Phase 1 result). Stripe has just returned but the
        // DB write below has not yet been observed by this instance.
        $stale = $booking->fresh();
        $this->assertSame(BookingStatus::REFUND_PENDING, $stale->status);

        // Racing path: terminate the booking out-of-band with its own audit
        // columns. We use DB::table to bypass model events / soft-delete
        // scopes and produce a clean "row was mutated under us" scenario.
        DB::table('bookings')->where('id', $booking->id)->update([
            'status' => BookingStatus::CANCELLED->value,
            'cancelled_at' => now(),
            'cancelled_by' => $racingActor->id,
            'cancelled_by_email' => $racingActor->email,
            'cancelled_by_role' => $racingActor->role->value,
            'cancelled_by_display' => $racingActor->name,
            'refund_error' => 'racing-path-audit-marker',
            'updated_at' => now(),
        ]);

        // Drive finalizeCancellation directly with the stale instance,
        // standing in for processRefund's call after a successful Stripe
        // refund. The method is private — we use reflection rather than
        // building a controller-level Stripe double for this targeted
        // regression.
        $service = app(CancellationService::class);
        $reflection = new \ReflectionMethod($service, 'finalizeCancellation');
        $reflection->setAccessible(true);

        $callerActor = User::factory()->create();
        $result = $reflection->invoke(
            $service,
            $stale,
            'rf_stale_refund_should_not_persist',
            5000,
            $callerActor,
        );

        // The locked re-read must win. The fresh row's audit columns set by
        // the racing path are preserved verbatim; the stale refund_id from
        // this caller is not persisted on top.
        $row = DB::table('bookings')->where('id', $booking->id)->first();
        $this->assertNotNull($row);
        $this->assertSame(BookingStatus::CANCELLED->value, $row->status);
        $this->assertSame('racing-path-audit-marker', $row->refund_error);
        $this->assertNull(
            $row->refund_id,
            'stale refund_id from caller must not overwrite the racing path\'s state',
        );
        $this->assertSame((int) $racingActor->id, (int) $row->cancelled_by);
        $this->assertSame($racingActor->email, $row->cancelled_by_email);

        // The returned model is the freshly locked CANCELLED row, not the
        // stale REFUND_PENDING input.
        $this->assertInstanceOf(Booking::class, $result);
        $this->assertSame($booking->id, $result->id);
        $this->assertSame(BookingStatus::CANCELLED, $result->status);

        // No additional cancellation event is dispatched: the idempotent
        // early return must not double-notify.
        Event::assertNotDispatched(BookingCancelled::class);
    }

    /**
     * Companion to the F-33 regression above: when no race occurs,
     * finalizeCancellation() must still drive the canonical
     * REFUND_PENDING → CANCELLED transition and persist refund metadata
     * onto the fresh locked row. Guards against an over-zealous
     * idempotency check accidentally short-circuiting the happy path.
     */
    public function test_finalize_cancellation_happy_path_persists_refund_metadata(): void
    {
        $booking = Booking::factory()
            ->for($this->user)
            ->for($this->room)
            ->create([
                'status' => BookingStatus::REFUND_PENDING,
                'check_in' => now()->addDays(5),
                'check_out' => now()->addDays(7),
                'payment_intent_id' => 'pi_f33_happy',
                'amount' => 10000,
            ]);

        $service = app(CancellationService::class);
        $reflection = new \ReflectionMethod($service, 'finalizeCancellation');
        $reflection->setAccessible(true);

        $result = $reflection->invoke(
            $service,
            $booking->fresh(),
            'rf_happy_refund_id',
            7500,
            $this->user,
        );

        $row = DB::table('bookings')->where('id', $booking->id)->first();
        $this->assertNotNull($row);
        $this->assertSame(BookingStatus::CANCELLED->value, $row->status);
        $this->assertSame('rf_happy_refund_id', $row->refund_id);
        $this->assertSame('succeeded', $row->refund_status);
        $this->assertSame(7500, (int) $row->refund_amount);
        $this->assertNull($row->refund_error);

        $this->assertSame(BookingStatus::CANCELLED, $result->status);
    }

    private function fakeStripeRefundClient(string $refundId): object
    {
        $capture = (object) [
            'calls' => 0,
            'payload' => null,
            'options' => null,
        ];

        $fakeStripe = new class($capture, $refundId) extends \Stripe\StripeClient
        {
            public object $refunds;

            public function __construct(object $capture, string $refundId)
            {
                $this->refunds = new class($capture, $refundId)
                {
                    public function __construct(
                        private object $capture,
                        private string $refundId,
                    ) {}

                    /**
                     * @param  array<string, mixed>  $payload
                     * @param  array<string, mixed>  $options
                     */
                    public function create(array $payload, array $options = []): object
                    {
                        $this->capture->calls++;
                        $this->capture->payload = $payload;
                        $this->capture->options = $options;

                        return (object) [
                            'id' => $this->refundId,
                        ];
                    }
                };
            }
        };

        $this->app->bind(\Stripe\StripeClient::class, static fn () => $fakeStripe);

        return $capture;
    }
}
