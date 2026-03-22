<?php

namespace Database\Factories;

use App\Models\Location;
use App\Models\Room;
use Illuminate\Database\Eloquent\Factories\Factory;

class RoomFactory extends Factory
{
    protected $model = Room::class;

    public function definition(): array
    {
        return [
            'location_id' => Location::factory(),
            'name' => $this->faker->word.' Room',
            'room_number' => (string) $this->faker->unique()->numberBetween(100, 999),
            'description' => $this->faker->sentence(10),
            'price' => $this->faker->randomFloat(2, 20, 200),
            'max_guests' => $this->faker->numberBetween(1, 8),
            'status' => $this->faker->randomElement(['available', 'booked', 'maintenance']),
        ];
    }

    /**
     * State: Room at a specific location.
     */
    public function forLocation(Location $location): static
    {
        return $this->state(fn (array $attributes) => [
            'location_id' => $location->id,
        ]);
    }

    /**
     * State: Available room.
     */
    public function available(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'available',
        ]);
    }
}
