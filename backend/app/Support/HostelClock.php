<?php

declare(strict_types=1);

namespace App\Support;

use Carbon\CarbonImmutable;
use DateTimeZone;
use InvalidArgumentException;

final class HostelClock
{
    private const DEFAULT_TIMEZONE = 'Asia/Ho_Chi_Minh';

    public static function timezone(): DateTimeZone
    {
        return new DateTimeZone(self::resolveTimezone());
    }

    /**
     * Resolve the configured hostel-local timezone as a validated IANA identifier.
     *
     * The configured value (booking.business_timezone, falling back to app.timezone)
     * must be a non-empty IANA identifier. Empty, whitespace-only, non-string, or
     * unrecognised values fail fast with InvalidArgumentException at this boundary
     * instead of surfacing as a low-level DateTimeZone exception deeper in the stack.
     * The canonical default applies only when both config keys are entirely absent.
     *
     * @return non-empty-string
     */
    private static function resolveTimezone(): string
    {
        $configured = config('booking.business_timezone', config('app.timezone', self::DEFAULT_TIMEZONE));

        $timezone = is_string($configured) ? trim($configured) : '';

        if ($timezone === '') {
            throw new InvalidArgumentException('Hostel timezone must not be empty.');
        }

        if (! in_array($timezone, DateTimeZone::listIdentifiers(), true)) {
            throw new InvalidArgumentException(
                sprintf('Invalid hostel timezone configured: "%s".', $timezone)
            );
        }

        return $timezone;
    }

    public static function now(): CarbonImmutable
    {
        return CarbonImmutable::now(self::timezone());
    }

    public static function today(): CarbonImmutable
    {
        return self::now()->startOfDay();
    }

    public static function todayDate(): string
    {
        return self::today()->toDateString();
    }

    public static function tomorrowDate(): string
    {
        return self::today()->addDay()->toDateString();
    }

    public static function parseDate(string $date): CarbonImmutable
    {
        $parsed = CarbonImmutable::createFromFormat('!Y-m-d', $date, self::timezone());

        if (! $parsed instanceof CarbonImmutable) {
            throw new InvalidArgumentException("Invalid hostel-local date: {$date}");
        }

        return $parsed;
    }

    public static function isDateBeforeToday(string $date): bool
    {
        return self::parseDate($date)->lt(self::today());
    }

    /**
     * Use only for UTC timestamp columns such as created_at/cancelled_at/refunded_at.
     *
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    public static function localDateRangeAsUtc(string $date): array
    {
        $start = self::parseDate($date)->startOfDay();
        $end = $start->endOfDay();

        return [
            $start->utc(),
            $end->utc(),
        ];
    }
}
