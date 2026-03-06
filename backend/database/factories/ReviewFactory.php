<?php

namespace Database\Factories;

use App\Models\Booking;
use App\Models\Review;
use App\Models\Room;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Review>
 */
class ReviewFactory extends Factory
{
    protected $model = Review::class;

    public function definition(): array
    {
        return [
            'title' => $this->faker->sentence(4),
            'content' => $this->faker->paragraph(3),
            'rating' => $this->faker->numberBetween(1, 5),
            'room_id' => Room::factory(),
            'user_id' => null,
            'booking_id' => null,
            'guest_name' => $this->faker->name(),
            'guest_email' => $this->faker->safeEmail(),
            'approved' => false,
        ];
    }

    /**
     * Set the review's booking, room, and user from an existing Booking.
     */
    public function forBooking(Booking $booking): static
    {
        return $this->state(fn () => [
            'booking_id' => $booking->id,
            'room_id' => $booking->room_id,
            'user_id' => $booking->user_id,
        ]);
    }

    public function approved(): static
    {
        return $this->state(['approved' => true]);
    }
}
