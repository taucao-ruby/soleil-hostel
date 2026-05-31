<?php

declare(strict_types=1);

namespace Tests\Unit\Support;

use App\Support\HostelClock;
use DateTimeZone;
use InvalidArgumentException;
use Tests\Unit\UnitTestCase;

/**
 * Pins the hostel-local timezone contract resolved by HostelClock:
 * the configured value must be a non-empty IANA identifier. Empty,
 * whitespace-only, non-string, or unrecognised values fail fast with
 * InvalidArgumentException at the boundary.
 */
class HostelClockTest extends UnitTestCase
{
    public function test_uses_configured_business_timezone(): void
    {
        config(['booking.business_timezone' => 'Asia/Ho_Chi_Minh']);

        $this->assertSame('Asia/Ho_Chi_Minh', HostelClock::timezone()->getName());
    }

    public function test_honours_a_non_default_valid_timezone(): void
    {
        config(['booking.business_timezone' => 'UTC']);

        $tz = HostelClock::timezone();

        $this->assertInstanceOf(DateTimeZone::class, $tz);
        $this->assertSame('UTC', $tz->getName());
        $this->assertSame('UTC', HostelClock::now()->getTimezone()->getName());
    }

    public function test_empty_timezone_is_rejected(): void
    {
        config(['booking.business_timezone' => '']);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Hostel timezone must not be empty.');

        HostelClock::timezone();
    }

    public function test_whitespace_only_timezone_is_rejected(): void
    {
        config(['booking.business_timezone' => '   ']);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Hostel timezone must not be empty.');

        HostelClock::timezone();
    }

    public function test_non_string_timezone_is_rejected(): void
    {
        config(['booking.business_timezone' => 123]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Hostel timezone must not be empty.');

        HostelClock::timezone();
    }

    public function test_surrounding_whitespace_is_trimmed_from_valid_timezone(): void
    {
        config(['booking.business_timezone' => '  Asia/Ho_Chi_Minh  ']);

        $this->assertSame('Asia/Ho_Chi_Minh', HostelClock::timezone()->getName());
    }

    public function test_invalid_identifier_is_rejected(): void
    {
        config(['booking.business_timezone' => 'Mars/Base']);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid hostel timezone configured: "Mars/Base".');

        HostelClock::timezone();
    }
}
