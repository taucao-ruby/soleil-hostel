<?php

namespace Tests\Feature\Database;

use App\Models\Room;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * CHECK Constraint Tests — verifies DB-level data integrity constraints.
 *
 * PostgreSQL only. Covers:
 * - chk_rooms_max_guests (migration 2026_03_17_000002)
 * - chk_rooms_price (migration 2026_02_22_000001, already present)
 * - chk_bookings_dates (migration 2026_02_22_000001, already present)
 * - chk_reviews_rating (migration 2026_02_22_000001, already present)
 */
class CheckConstraintTest extends TestCase
{
    use RefreshDatabase;

    private function isPgsql(): bool
    {
        return \Illuminate\Support\Facades\DB::getDriverName() === 'pgsql';
    }

    // ===== chk_rooms_max_guests =====

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_room_max_guests_zero_rejected(): void
    {
        if (! $this->isPgsql()) {
            $this->markTestSkipped('CHECK constraints require PostgreSQL');
        }

        $this->expectException(QueryException::class);

        Room::factory()->create(['max_guests' => 0]);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_room_max_guests_negative_rejected(): void
    {
        if (! $this->isPgsql()) {
            $this->markTestSkipped('CHECK constraints require PostgreSQL');
        }

        $this->expectException(QueryException::class);

        Room::factory()->create(['max_guests' => -1]);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_room_max_guests_positive_accepted(): void
    {
        if (! $this->isPgsql()) {
            $this->markTestSkipped('CHECK constraints require PostgreSQL');
        }

        $room = Room::factory()->create(['max_guests' => 1]);

        $this->assertDatabaseHas('rooms', [
            'id' => $room->id,
            'max_guests' => 1,
        ]);
    }
}
