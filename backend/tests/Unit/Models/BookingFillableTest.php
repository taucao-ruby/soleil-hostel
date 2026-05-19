<?php

namespace Tests\Unit\Models;

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\Room;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Mass-assignment surface for Booking.
 *
 * Regression contract (A-1 defense-in-depth):
 * - $fillable is restricted to user-supplied booking inputs only.
 * - State-machine columns (status), authorship (user_id, deleted_by),
 *   payment/deposit/refund columns, and cancellation audit columns
 *   MUST NOT be settable through mass assignment from a controller.
 * - Trusted code paths that need to write those columns must use
 *   forceFill/forceCreate or direct property assignment.
 *
 * Earlier history (H-01): cancellation audit columns were originally
 * outside $fillable, causing $booking->update(['cancellation_reason' => ...])
 * to silently drop the value. The fix landed those writes inside trusted
 * services using forceFill, so the audit data persists without re-opening
 * the mass-assignment surface to user input.
 */
class BookingFillableTest extends TestCase
{
    use RefreshDatabase;

    public function test_fillable_is_restricted_to_user_input_columns(): void
    {
        $fillable = (new Booking)->getFillable();

        $this->assertSame(
            ['room_id', 'check_in', 'check_out', 'guest_name', 'guest_email'],
            $fillable,
            'Booking $fillable must be limited to user-supplied booking input.'
        );
    }

    public function test_protected_state_columns_are_not_fillable(): void
    {
        $fillable = (new Booking)->getFillable();

        $protected = [
            'status',
            'user_id',
            'deleted_by',
            'payment_intent_id',
            'amount',
            'deposit_amount',
            'deposit_collected_at',
            'deposit_status',
            'refund_id',
            'refund_status',
            'refund_amount',
            'refund_error',
            'cancelled_at',
            'cancelled_by',
            'cancelled_by_email',
            'cancelled_by_role',
            'cancelled_by_display',
            'cancellation_reason',
        ];

        foreach ($protected as $column) {
            $this->assertNotContains(
                $column,
                $fillable,
                "Booking column [{$column}] must NOT be in \$fillable — defense-in-depth against controller mass assignment."
            );
        }
    }

    /**
     * A-1 regression: Booking::create() from user-shaped input must not
     * promote a pending booking to confirmed nor write refund/cancellation
     * audit columns, even if the attacker spreads $request->all() in.
     */
    public function test_create_from_user_input_cannot_set_protected_columns(): void
    {
        $room = Room::factory()->available()->ready()->create();

        $booking = Booking::create([
            // Legitimate user input
            'room_id' => $room->id,
            'check_in' => Carbon::tomorrow()->toDateString(),
            'check_out' => Carbon::tomorrow()->addDays(2)->toDateString(),
            'guest_name' => 'Honest Guest',
            'guest_email' => 'honest@example.com',
            // Attempted mass-assign of protected columns:
            'status' => BookingStatus::CONFIRMED->value,
            'user_id' => 99999,
            'amount' => 999999,
            'payment_intent_id' => 'pi_evil',
            'refund_id' => 're_evil',
            'refund_status' => 'succeeded',
            'refund_amount' => 100000,
            'cancelled_at' => now(),
            'cancelled_by' => 1,
            'cancelled_by_email' => 'attacker@example.com',
            'cancelled_by_role' => 'admin',
            'cancelled_by_display' => 'Attacker',
            'cancellation_reason' => 'spoofed',
            'deleted_by' => 1,
        ]);

        $booking->refresh();

        // status falls back to the DB default ('pending'); not the attacker's 'confirmed'
        $this->assertNotSame(BookingStatus::CONFIRMED, $booking->status);

        // None of the protected columns may have been persisted from the input array
        $this->assertNull($booking->user_id, 'user_id must NOT be mass-assignable');
        $this->assertNull($booking->payment_intent_id, 'payment_intent_id must NOT be mass-assignable');
        $this->assertNull($booking->refund_id, 'refund_id must NOT be mass-assignable');
        $this->assertNull($booking->refund_status, 'refund_status must NOT be mass-assignable');
        $this->assertNull($booking->refund_amount, 'refund_amount must NOT be mass-assignable');
        $this->assertNull($booking->cancelled_at, 'cancelled_at must NOT be mass-assignable');
        $this->assertNull($booking->cancelled_by, 'cancelled_by must NOT be mass-assignable');
        $this->assertNull($booking->cancelled_by_email, 'cancelled_by_email must NOT be mass-assignable');
        $this->assertNull($booking->cancelled_by_role, 'cancelled_by_role must NOT be mass-assignable');
        $this->assertNull($booking->cancelled_by_display, 'cancelled_by_display must NOT be mass-assignable');
        $this->assertNull($booking->cancellation_reason, 'cancellation_reason must NOT be mass-assignable');
        $this->assertNull($booking->deleted_by, 'deleted_by must NOT be mass-assignable');

        // The legitimate user-input fields landed correctly
        $this->assertSame($room->id, $booking->room_id);
        $this->assertSame('Honest Guest', $booking->guest_name);
        $this->assertSame('honest@example.com', $booking->guest_email);
    }

    /**
     * Defense-in-depth: $booking->update() from user input cannot promote
     * a pending booking to confirmed, cancel it, or rewrite refund state.
     */
    public function test_update_from_user_input_cannot_mutate_protected_columns(): void
    {
        $booking = Booking::factory()->pending()->create();
        $originalStatus = $booking->status;

        $booking->update([
            // Legitimate user-input update
            'guest_name' => 'Renamed Guest',
            // Attempted mass-assign of protected columns:
            'status' => BookingStatus::CONFIRMED->value,
            'refund_id' => 're_evil',
            'refund_status' => 'succeeded',
            'refund_amount' => 50000,
            'cancelled_at' => now(),
            'cancelled_by' => 1,
            'cancellation_reason' => 'spoofed',
        ]);

        $booking->refresh();

        // The legitimate field updated…
        $this->assertSame('Renamed Guest', $booking->guest_name);
        // …but the protected columns did NOT
        $this->assertSame($originalStatus, $booking->status);
        $this->assertNull($booking->refund_id);
        $this->assertNull($booking->refund_status);
        $this->assertNull($booking->refund_amount);
        $this->assertNull($booking->cancelled_at);
        $this->assertNull($booking->cancelled_by);
        $this->assertNull($booking->cancellation_reason);
    }

    /**
     * Trusted services rely on forceFill() to persist cancellation audit.
     * This is the H-01 regression — the audit columns must still land when
     * written through the trusted path.
     */
    public function test_cancellation_audit_persists_via_force_fill(): void
    {
        $booking = Booking::factory()->pending()->create();
        $admin = User::factory()->create();

        $booking->forceFill([
            'status' => BookingStatus::CANCELLED,
            'cancelled_at' => now(),
            'cancelled_by' => $admin->id,
            'cancelled_by_email' => $admin->email,
            'cancelled_by_role' => 'admin',
            'cancelled_by_display' => $admin->name,
            'cancellation_reason' => 'Changed travel plans',
        ])->save();

        $booking->refresh();

        $this->assertSame(BookingStatus::CANCELLED, $booking->status);
        $this->assertSame('Changed travel plans', $booking->cancellation_reason);
        $this->assertSame($admin->id, $booking->cancelled_by);
        $this->assertSame($admin->email, $booking->cancelled_by_email);
        $this->assertSame('admin', $booking->cancelled_by_role);
        $this->assertSame($admin->name, $booking->cancelled_by_display);
        $this->assertNotNull($booking->cancelled_at);
    }
}
