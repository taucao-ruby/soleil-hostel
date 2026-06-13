<?php

namespace Tests\Feature\Database;

use App\Models\AdminAuditLog;
use App\Models\AiProposalEvent;
use App\Models\Booking;
use App\Models\DepositEvent;
use App\Models\Review;
use App\Models\Room;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * FK Delete Policy Tests — verifies hardened delete policies.
 *
 * These tests require PostgreSQL (FKs are not applied in SQLite).
 * Covers migrations: 2026_03_17_000001_harden_fk_delete_policies.php,
 * 2026_06_12_000001_restrict_deposit_events_booking_fk.php (memo D2/F-89),
 * plus the ledger actor SET NULL semantics ratified by
 * docs/DECISION_LEDGER_IMMUTABILITY_FK.md (D1/F-90) — these UPDATEs must
 * pass the append-only triggers from 2026_06_12_000002.
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

    // ===== ledger actor FKs → SET NULL (memo D1: user deletion must survive the append-only triggers) =====

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_user_deletion_nullifies_deposit_event_actor_id(): void
    {
        if (! $this->isPgsql()) {
            $this->markTestSkipped('FK policies require PostgreSQL');
        }

        $actor = User::factory()->create();
        $booking = Booking::factory()->cancelled()->create();
        $event = DepositEvent::create([
            'booking_id' => $booking->id,
            'from_status' => 'collected',
            'to_status' => 'refunded',
            'refund_percent' => 100,
            'actor_id' => $actor->id,
            'actor_email' => $actor->email,
            'actor_role' => 'admin',
        ]);

        $actor->forceDelete();

        $event->refresh();
        $this->assertNull($event->actor_id);
        $this->assertSame($actor->email, $event->actor_email);
        $this->assertDatabaseHas('deposit_events', ['id' => $event->id]);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_user_deletion_nullifies_admin_audit_log_actor_id(): void
    {
        if (! $this->isPgsql()) {
            $this->markTestSkipped('FK policies require PostgreSQL');
        }

        $actor = User::factory()->create();
        $log = AdminAuditLog::create([
            'actor_id' => $actor->id,
            'actor_email' => $actor->email,
            'actor_role' => 'admin',
            'action' => 'booking.force_delete',
            'resource_type' => 'booking',
            'resource_id' => 1,
        ]);

        $actor->forceDelete();

        $log->refresh();
        $this->assertNull($log->actor_id);
        $this->assertSame($actor->email, $log->actor_email);
        $this->assertDatabaseHas('admin_audit_logs', ['id' => $log->id]);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_user_deletion_nullifies_ai_proposal_event_user_id(): void
    {
        if (! $this->isPgsql()) {
            $this->markTestSkipped('FK policies require PostgreSQL');
        }

        $actor = User::factory()->create();
        $event = AiProposalEvent::create([
            'user_id' => $actor->id,
            'actor_email' => $actor->email,
            'actor_role' => 'user',
            'proposal_hash' => str_repeat('a', 64),
            'action_type' => 'cancel_booking',
            'user_decision' => 'confirmed',
        ]);

        $actor->forceDelete();

        $event->refresh();
        $this->assertNull($event->user_id);
        $this->assertSame($actor->email, $event->actor_email);
        $this->assertDatabaseHas('ai_proposal_events', ['id' => $event->id]);
    }

    // ===== deposit_events.booking_id → RESTRICT (memo D2, F-89) =====

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_booking_hard_delete_blocked_when_deposit_events_exist(): void
    {
        if (! $this->isPgsql()) {
            $this->markTestSkipped('FK policies require PostgreSQL');
        }

        $booking = Booking::factory()->cancelled()->create();
        $event = DepositEvent::create([
            'booking_id' => $booking->id,
            'from_status' => 'collected',
            'to_status' => 'forfeited',
            'refund_percent' => 0,
        ]);
        $booking->delete(); // soft-delete first, mirroring the force-delete flow

        try {
            // Savepoint so the 23503 doesn't abort RefreshDatabase's outer transaction.
            DB::transaction(fn () => $booking->forceDelete());

            $this->fail('Booking hard-delete was accepted despite deposit_events history — RESTRICT did not fire');
        } catch (QueryException $e) {
            $sqlState = $e->errorInfo[0] ?? (string) $e->getCode();

            $this->assertSame(
                '23503',
                $sqlState,
                'Expected SQLSTATE 23503 (foreign_key_violation), got '.$sqlState.': '.$e->getMessage()
            );
        }

        $this->assertDatabaseHas('deposit_events', ['id' => $event->id]);
        $this->assertDatabaseHas('bookings', ['id' => $booking->id]);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_booking_hard_delete_succeeds_without_deposit_events(): void
    {
        if (! $this->isPgsql()) {
            $this->markTestSkipped('FK policies require PostgreSQL');
        }

        $booking = Booking::factory()->cancelled()->create();
        $booking->delete();

        $booking->forceDelete();

        $this->assertDatabaseMissing('bookings', ['id' => $booking->id]);
    }
}
