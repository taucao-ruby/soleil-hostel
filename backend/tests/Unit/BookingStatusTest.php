<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Enums\BookingStatus;
use PHPUnit\Framework\TestCase;

final class BookingStatusTest extends TestCase
{
    public function test_active_status_set_matches_room_blocking_invariant(): void
    {
        $this->assertSame([
            BookingStatus::PENDING->value,
            BookingStatus::CONFIRMED->value,
        ], BookingStatus::ACTIVE_STATUSES);

        $this->assertNotContains(BookingStatus::CANCELLED->value, BookingStatus::ACTIVE_STATUSES);
        $this->assertNotContains(BookingStatus::REFUND_PENDING->value, BookingStatus::ACTIVE_STATUSES);
        $this->assertNotContains(BookingStatus::REFUND_FAILED->value, BookingStatus::ACTIVE_STATUSES);
    }
}
