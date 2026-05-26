<?php

declare(strict_types=1);

namespace App\Support;

use Carbon\CarbonImmutable;
use DateTimeZone;
use InvalidArgumentException;

final class HostelClock
{
    public static function timezone(): DateTimeZone
    {
        $timezone = config('booking.business_timezone', config('app.timezone', 'Asia/Ho_Chi_Minh'));

        if (! is_string($timezone) || trim($timezone) === '') {
            $timezone = 'Asia/Ho_Chi_Minh';
        }

        return new DateTimeZone($timezone);
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
