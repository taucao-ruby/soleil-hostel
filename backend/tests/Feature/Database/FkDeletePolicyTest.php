<?php

namespace Tests\Feature\Database;

use App\Models\Booking;
use App\Models\Review;
use App\Models\Room;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * FK Delete Policy Tests — verifies hardened delete policies.
 *
 * These tests require PostgreSQL (FKs are not applied in SQLite).
 * Covers migration: 2026_03_17_000001_harden_fk_delete_policies.php
 */
class FkDeletePolicyTest extends TestCase
{
    use RefreshDatabase;

    private function isPgsql(): bool
    {
        return \Illuminate\Support\Facades\DB::getDriverName() === 'pgsql';
    }

    // ===== bookings.room_id → RESTRICT =====

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_room_deletion_blocked_when_booking_exists(): void
    {
        if (! $this->isPgsql()) {
            $this->markTestSkipped('FK policies require PostgreSQL');
        }

        $room = Room::factory()->available()->create();
        Booking::factory()->forRoom($room)->cancelled()->create();

        $this->expectException(QueryException::class);
        $room->delete();
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_room_deletion_succeeds_when_no_bookings(): void
    {
        if (! $this->isPgsql()) {
            $this->markTestSkipped('FK policies require PostgreSQL');
        }

        $room = Room::factory()->available()->create();

        $room->delete();

        $this->assertDatabaseMissing('rooms', ['id' => $room->id]);
    }

    // ===== bookings.user_id → SET NULL =====

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_user_deletion_nullifies_booking_user_id(): void
    {
        if (! $this->isPgsql()) {
            $this->markTestSkipped('FK policies require PostgreSQL');
        }

        $user = User::factory()->create();
        $booking = Booking::factory()->forUser($user)->cancelled()->create();

        $user->forceDelete();

        $booking->refresh();
        $this->assertNull($booking->user_id);
        $this->assertDatabaseHas('bookings', ['id' => $booking->id]);
    }

    // ===== reviews.user_id → SET NULL =====

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_user_deletion_nullifies_review_user_id(): void
    {
        if (! $this->isPgsql()) {
            $this->markTestSkipped('FK policies require PostgreSQL');
        }

        $user = User::factory()->create();
        $booking = Booking::factory()->forUser($user)->expired()->create();
        $review = Review::factory()->forBooking($booking)->create();

        $user->forceDelete();

        $review->refresh();
        $this->assertNull($review->user_id);
        $this->assertDatabaseHas('reviews', ['id' => $review->id]);
    }

    // ===== reviews.room_id → SET NULL (protected by bookings.room_id RESTRICT) =====

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_room_with_booking_and_review_blocks_deletion(): void
    {
        if (! $this->isPgsql()) {
            $this->markTestSkipped('FK policies require PostgreSQL');
        }

        // Every review requires a booking (booking_id NOT NULL).
        // bookings.room_id RESTRICT blocks room deletion when bookings exist.
        // This implicitly protects reviews.room_id as well.
        $room = Room::factory()->available()->create();
        $booking = Booking::factory()->forRoom($room)->expired()->create();
        Review::factory()->forBooking($booking)->create();

        $this->expectException(QueryException::class);
        $room->delete();
    }
}
