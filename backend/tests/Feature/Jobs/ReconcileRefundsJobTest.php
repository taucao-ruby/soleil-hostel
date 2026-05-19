<?php

declare(strict_types=1);

namespace Tests\Feature\Jobs;

use App\Enums\BookingStatus;
use App\Jobs\ReconcileRefundsJob;
use App\Models\Booking;
use App\Models\Room;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * CONC-006 — ReconcileRefundsJob must survive deleted/null users.
 *
 * The FK bookings.user_id is ON DELETE SET NULL: when a guest account is
 * removed, the booking row stays with user_id = NULL. The job previously
 * dereferenced $booking->user->stripe() / ->refund() and crashed with NPE.
 *
 * These tests verify the null-safe paths added by CONC-006:
 * - Null user + no application Stripe secret -> log warning + skip, do NOT throw.
 * - Null user but guest_email present -> recipient resolution falls back to it.
 * - Null user AND null guest_email -> log warning + continue without throwing.
 */
final class ReconcileRefundsJobTest extends TestCase
{
    private User $guest;

    private Room $room;

    protected function setUp(): void
    {
        parent::setUp();
        $this->guest = User::factory()->create();
        $this->room = Room::factory()->create();

        // Force resolveStripeClientFor to return null when no user is attached.
        config(['cashier.secret' => '']);
    }

    public function test_pending_refund_with_null_user_is_skipped_without_throwing(): void
    {
        $booking = $this->makeBooking(
            status: BookingStatus::REFUND_PENDING,
            withUser: false,
            paymentIntentId: 'pi_test_orphan_pending',
            refundId: 're_test_orphan_pending',
            staleMinutes: 30,
        );

        // Sanity: bookings.user_id is null because the FK uses ON DELETE SET NULL.
        $this->assertNull($booking->fresh()?->user_id);

        // The job must NOT throw even though Stripe is unreachable for an orphan booking.
        (new ReconcileRefundsJob)->handle();

        // Status unchanged (we never reached Stripe).
        $this->assertSame(BookingStatus::REFUND_PENDING, $booking->fresh()->status);
    }

    public function test_failed_refund_with_null_user_skips_retry_without_throwing(): void
    {
        $booking = $this->makeBooking(
            status: BookingStatus::REFUND_FAILED,
            withUser: false,
            paymentIntentId: 'pi_test_orphan_failed',
            refundId: null,
            staleMinutes: 30,
        );

        // Pre-condition: booking has a non-zero amount so calculateRefundAmount > 0
        // and the retry branch actually attempts a Stripe call.
        // amount is not mass-assignable (A-1); use forceFill for protected fields.
        $booking->fill([
            'check_in' => Carbon::now()->addDays(7),
            'check_out' => Carbon::now()->addDays(9),
        ])->forceFill([
            'amount' => 10_000,
        ])->save();

        (new ReconcileRefundsJob)->handle();

        // Booking is still REFUND_FAILED; no exception bubbled up.
        $this->assertSame(BookingStatus::REFUND_FAILED, $booking->fresh()->status);
    }

    public function test_recipient_email_falls_back_to_guest_email_when_user_is_null(): void
    {
        $booking = $this->makeBooking(
            status: BookingStatus::REFUND_FAILED,
            withUser: false,
            paymentIntentId: 'pi_test_orphan_email',
            refundId: null,
            staleMinutes: 30,
        );

        $booking->update(['guest_email' => 'orphan-guest@example.test']);

        $email = $this->callPrivate(new ReconcileRefundsJob, 'resolveRecipientEmail', [$booking->fresh()]);

        $this->assertSame('orphan-guest@example.test', $email);
    }

    public function test_recipient_email_returns_user_email_when_user_present(): void
    {
        $booking = $this->makeBooking(
            status: BookingStatus::REFUND_FAILED,
            withUser: true,
            paymentIntentId: 'pi_test_user_attached',
            refundId: null,
            staleMinutes: 30,
        );

        $booking->update(['guest_email' => 'guest@example.test']);

        $email = $this->callPrivate(
            new ReconcileRefundsJob,
            'resolveRecipientEmail',
            [$booking->fresh()->load('user')],
        );

        $this->assertSame($this->guest->email, $email);
    }

    public function test_recipient_email_returns_null_when_user_and_guest_email_missing(): void
    {
        // The bookings table enforces guest_email NOT NULL, so we exercise
        // the helper against an unsaved instance where guest_email has been
        // explicitly cleared in memory. This is the defensive path the
        // helper guards against — production rows can't reach it but the
        // helper must be null-safe even so.
        $booking = new Booking([
            'guest_email' => null,
        ]);
        $booking->setRelation('user', null);

        $email = $this->callPrivate(new ReconcileRefundsJob, 'resolveRecipientEmail', [$booking]);

        $this->assertNull($email);
    }

    // ===== Helpers =====

    private function makeBooking(
        BookingStatus $status,
        bool $withUser,
        string $paymentIntentId,
        ?string $refundId,
        int $staleMinutes,
    ): Booking {
        $booking = Booking::factory()
            ->for($this->room)
            ->state([
                'user_id' => $withUser ? $this->guest->id : null,
                'status' => $status,
                'payment_intent_id' => $paymentIntentId,
                'refund_id' => $refundId,
                'amount' => 5_000,
            ])
            ->create();

        // Backdate updated_at so the job's stale-threshold filter selects the row.
        DB::table('bookings')
            ->where('id', $booking->id)
            ->update(['updated_at' => Carbon::now()->subMinutes($staleMinutes)]);

        return $booking->fresh();
    }

    /**
     * @param  array<int, mixed>  $args
     */
    private function callPrivate(object $object, string $method, array $args): mixed
    {
        $ref = new \ReflectionMethod($object, $method);
        $ref->setAccessible(true);

        return $ref->invokeArgs($object, $args);
    }
}
