<?php

declare(strict_types=1);

namespace App\AiHarness\Evaluation;

use App\Enums\BookingStatus;
use App\Enums\DepositStatus;
use App\Enums\PaymentPolicy;
use App\Enums\PaymentStatus;
use App\Enums\RoomReadinessStatus;
use App\Enums\UserRole;
use App\Models\Booking;
use App\Models\Location;
use App\Models\Room;
use App\Models\User;
use Closure;
use Database\Seeders\PolicyDocumentSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Creates rollback-scoped reference data for deterministic AI eval runs.
 */
final class AiEvalFixtureFactory
{
    /**
     * @param  Closure(): int  $callback
     */
    public function runWithRollback(Closure $callback): int
    {
        $initialTransactionLevel = DB::transactionLevel();

        DB::beginTransaction();

        try {
            $this->seedReferenceData();

            return $callback();
        } finally {
            while (DB::transactionLevel() > $initialTransactionLevel) {
                DB::rollBack();
            }
        }
    }

    public function createActor(UserRole $role): User
    {
        $email = "ai-eval-{$role->value}@example.test";
        $actor = User::query()->firstOrNew(['email' => $email]);

        $actor->forceFill([
            'name' => "AI Eval {$role->value}",
            'email' => $email,
            'email_verified_at' => now(),
            'role' => $role,
        ]);

        if (! $actor->exists) {
            $actor->password = Hash::make(Str::random(64));
        }

        $actor->save();
        $this->seedBookings($actor);

        return $actor;
    }

    public function resolveAliases(string $input): string
    {
        $resolved = preg_replace_callback(
            '/\bbooking\s+#(\d+)\b/iu',
            fn (array $matches): string => 'booking #'.$this->resolveBookingAlias((int) $matches[1]),
            $input,
        );

        return $resolved ?? $input;
    }

    private function seedReferenceData(): void
    {
        app(PolicyDocumentSeeder::class)->run();

        $location = Location::query()->updateOrCreate(
            ['slug' => 'ai-eval-hostel'],
            [
                'name' => 'AI Eval Hostel',
                'address' => '1 Eval Street',
                'city' => 'Hue',
                'description' => 'Rollback-scoped AI eval location.',
                'amenities' => ['wifi'],
                'images' => [],
                'is_active' => true,
                'total_rooms' => 5,
            ],
        );

        foreach (range(1, 5) as $slot) {
            Room::query()->updateOrCreate(
                [
                    'location_id' => $location->id,
                    'room_number' => $this->roomNumber($slot),
                ],
                [
                    'name' => "AI Eval Room {$slot}",
                    'description' => 'Rollback-scoped AI eval room.',
                    'price' => 100000 + ($slot * 10000),
                    'max_guests' => 4,
                    'status' => 'available',
                    'readiness_status' => RoomReadinessStatus::READY,
                    'room_type_code' => 'eval-standard',
                    'room_tier' => 1,
                ],
            );
        }
    }

    private function seedBookings(User $actor): void
    {
        $aliases = [33, 42, 50, 75, 99, 100, 101];
        $rooms = Room::query()
            ->where('room_number', 'like', 'EVAL-%')
            ->orderBy('room_number')
            ->get();

        if ($rooms->isEmpty()) {
            throw new \LogicException('AI eval room fixtures were not created.');
        }

        foreach ($aliases as $index => $alias) {
            $room = $rooms->get($index % $rooms->count());
            if (! $room instanceof Room) {
                throw new \LogicException('AI eval room fixture lookup failed.');
            }

            $checkIn = now()->addYears(5)->startOfYear()->addDays($index * 4);
            $checkOut = $checkIn->clone()->addDays(2);
            $booking = Booking::query()->firstOrNew([
                'guest_email' => $this->bookingEmail($alias),
            ]);

            $attributes = [
                'room_id' => $room->id,
                'check_in' => $checkIn,
                'check_out' => $checkOut,
                'guest_name' => "AI Eval Guest {$alias}",
                'guest_email' => $this->bookingEmail($alias),
                'status' => BookingStatus::PENDING,
            ];

            $optionalAttributes = [
                'user_id' => $actor->id,
                'number_of_guests' => 2,
                'special_requests' => null,
                'payment_policy' => PaymentPolicy::PAY_AT_PROPERTY,
                'payment_status' => PaymentStatus::OFFLINE_DUE,
                'payment_currency' => 'vnd',
                'amount_capturable' => 0,
                'amount_received' => 0,
                'deposit_status' => DepositStatus::NONE,
            ];

            foreach ($optionalAttributes as $column => $value) {
                if (Schema::hasColumn('bookings', $column)) {
                    $attributes[$column] = $value;
                }
            }

            $booking->forceFill($attributes);

            $booking->save();
        }
    }

    private function resolveBookingAlias(int $alias): int
    {
        return (int) (Booking::query()
            ->where('guest_email', $this->bookingEmail($alias))
            ->value('id') ?? $alias);
    }

    private function bookingEmail(int $alias): string
    {
        return "ai-eval-booking-{$alias}@example.test";
    }

    private function roomNumber(int $slot): string
    {
        return sprintf('EVAL-%02d', $slot);
    }
}
