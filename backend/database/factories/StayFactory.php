<?php

namespace Database\Factories;

use App\Enums\StayStatus;
use App\Models\Booking;
use App\Models\Stay;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\Factory;

class StayFactory extends Factory
{
    protected $model = Stay::class;

    public function definition(): array
    {
        $checkIn = Carbon::now()->addDays($this->faker->numberBetween(1, 30))->startOfDay();

        return [
            'booking_id' => Booking::factory(),
            'stay_status' => StayStatus::EXPECTED,
            'scheduled_check_in_at' => $checkIn,
            'scheduled_check_out_at' => $checkIn->clone()->addDays($this->faker->numberBetween(1, 7)),
            'actual_check_in_at' => null,
            'actual_check_out_at' => null,
            'late_checkout_minutes' => 0,
            'late_checkout_fee_amount' => null,
            'no_show_at' => null,
            'checked_in_by' => null,
            'checked_out_by' => null,
        ];
    }

    /**
     * State: guest is expected to arrive (pre-arrival).
     */
    public function expected(): static
    {
        return $this->state(fn (array $attributes) => [
            'stay_status' => StayStatus::EXPECTED,
            'actual_check_in_at' => null,
            'actual_check_out_at' => null,
        ]);
    }

    /**
     * State: guest is currently checked in.
     */
    public function inHouse(): static
    {
        $checkIn = Carbon::now()->subHours($this->faker->numberBetween(1, 12));

        return $this->state(fn (array $attributes) => [
            'stay_status' => StayStatus::IN_HOUSE,
            'scheduled_check_in_at' => $checkIn,
            'actual_check_in_at' => $checkIn,
            'actual_check_out_at' => null,
        ]);
    }

    /**
     * State: guest is in late checkout.
     */
    public function lateCheckout(): static
    {
        $checkIn = Carbon::now()->subDays(2);
        $scheduledCheckOut = Carbon::now()->subHours(2);
        $lateMinutes = $this->faker->numberBetween(30, 240);

        return $this->state(fn (array $attributes) => [
            'stay_status' => StayStatus::LATE_CHECKOUT,
            'scheduled_check_in_at' => $checkIn,
            'actual_check_in_at' => $checkIn,
            'scheduled_check_out_at' => $scheduledCheckOut,
            'actual_check_out_at' => null,
            'late_checkout_minutes' => $lateMinutes,
            'late_checkout_fee_amount' => $lateMinutes * 100, // $1 per minute in cents
        ]);
    }

    /**
     * State: guest has checked out.
     */
    public function checkedOut(): static
    {
        $checkIn = Carbon::now()->subDays($this->faker->numberBetween(3, 10));
        $checkOut = Carbon::now()->subDays($this->faker->numberBetween(1, 2));

        return $this->state(fn (array $attributes) => [
            'stay_status' => StayStatus::CHECKED_OUT,
            'scheduled_check_in_at' => $checkIn,
            'actual_check_in_at' => $checkIn,
            'scheduled_check_out_at' => $checkOut,
            'actual_check_out_at' => $checkOut,
        ]);
    }

    /**
     * State: guest was a no-show.
     */
    public function noShow(): static
    {
        $scheduledCheckIn = Carbon::now()->subDays($this->faker->numberBetween(1, 5));

        return $this->state(fn (array $attributes) => [
            'stay_status' => StayStatus::NO_SHOW,
            'scheduled_check_in_at' => $scheduledCheckIn,
            'actual_check_in_at' => null,
            'no_show_at' => $scheduledCheckIn->clone()->addHours($this->faker->numberBetween(2, 8)),
        ]);
    }

    /**
     * State: associate with a specific booking.
     */
    public function forBooking(Booking $booking): static
    {
        return $this->state(fn (array $attributes) => [
            'booking_id' => $booking->id,
        ]);
    }

    /**
     * State: checked in by a specific staff member.
     */
    public function checkedInBy(User $user): static
    {
        return $this->state(fn (array $attributes) => [
            'checked_in_by' => $user->id,
        ]);
    }
}
