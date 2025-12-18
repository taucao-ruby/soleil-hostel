<?php

namespace Database\Factories;

use App\Models\Booking;
use App\Models\Room;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\Factory;

class BookingFactory extends Factory
{
    protected $model = Booking::class;

    public function definition(): array
    {
        $checkIn = Carbon::now()->addDays($this->faker->numberBetween(1, 30))->startOfDay();
        $checkOut = $checkIn->clone()->addDays($this->faker->numberBetween(1, 7));

        return [
            'room_id' => Room::factory(),
            'user_id' => User::factory(),
            'check_in' => $checkIn,
            'check_out' => $checkOut,
            'guest_name' => $this->faker->name(),
            'guest_email' => $this->faker->safeEmail(),
            'status' => Booking::STATUS_PENDING,
        ];
    }

    /**
     * Create a confirmed booking.
     */
    public function confirmed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => Booking::STATUS_CONFIRMED,
        ]);
    }

    /**
     * Create a cancelled booking.
     */
    public function cancelled(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => Booking::STATUS_CANCELLED,
        ]);
    }

    /**
     * Create a pending booking.
     */
    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => Booking::STATUS_PENDING,
        ]);
    }

    /**
     * Create booking for specific room.
     */
    public function forRoom(Room $room): static
    {
        return $this->state(fn (array $attributes) => [
            'room_id' => $room->id,
        ]);
    }

    /**
     * Create booking for specific user.
     */
    public function forUser(User $user): static
    {
        return $this->state(fn (array $attributes) => [
            'user_id' => $user->id,
        ]);
    }

    /**
     * Create booking for specific dates.
     */
    public function forDates(Carbon $checkIn, Carbon $checkOut): static
    {
        return $this->state(fn (array $attributes) => [
            'check_in' => $checkIn,
            'check_out' => $checkOut,
        ]);
    }

    /**
     * Create booking for today check-in.
     */
    public function todayCheckIn(): static
    {
        $today = Carbon::now()->startOfDay();
        return $this->state(fn (array $attributes) => [
            'check_in' => $today,
            'check_out' => $today->clone()->addDays(1),
        ]);
    }

    /**
     * Create booking with specific duration (days).
     */
    public function forDays(int $days): static
    {
        $checkIn = Carbon::now()->addDays($this->faker->numberBetween(1, 30))->startOfDay();
        $checkOut = $checkIn->clone()->addDays($days);

        return $this->state(fn (array $attributes) => [
            'check_in' => $checkIn,
            'check_out' => $checkOut,
        ]);
    }

    /**
     * Create a soft deleted booking.
     * 
     * @param User|null $deletedBy User who deleted the booking
     */
    public function trashed(?User $deletedBy = null): static
    {
        return $this->state(fn (array $attributes) => [
            'deleted_at' => Carbon::now()->subDays($this->faker->numberBetween(1, 30)),
            'deleted_by' => $deletedBy?->id ?? User::factory(),
        ]);
    }

    /**
     * Create a soft deleted booking with specific deletion date.
     */
    public function trashedAt(Carbon $deletedAt, ?User $deletedBy = null): static
    {
        return $this->state(fn (array $attributes) => [
            'deleted_at' => $deletedAt,
            'deleted_by' => $deletedBy?->id ?? User::factory(),
        ]);
    }
}
