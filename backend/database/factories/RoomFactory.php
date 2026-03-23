<?php

namespace Database\Factories;

use App\Enums\RoomReadinessStatus;
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
            'room_type_code' => null,
            'room_tier' => 1,
            'status' => $this->faker->randomElement(['available', 'booked', 'maintenance']),
            'readiness_status' => RoomReadinessStatus::READY,
            'readiness_updated_at' => null,
            'readiness_updated_by' => null,
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

    /**
     * State: physically ready room.
     */
    public function ready(): static
    {
        return $this->state(fn (array $attributes) => [
            'readiness_status' => RoomReadinessStatus::READY,
        ]);
    }

    /**
     * State: physically dirty room awaiting housekeeping.
     */
    public function dirty(): static
    {
        return $this->state(fn (array $attributes) => [
            'readiness_status' => RoomReadinessStatus::DIRTY,
        ]);
    }

    /**
     * State: room blocked from service.
     */
    public function outOfService(): static
    {
        return $this->state(fn (array $attributes) => [
            'readiness_status' => RoomReadinessStatus::OUT_OF_SERVICE,
        ]);
    }

    /**
     * State: set operational comparability fields.
     */
    public function classified(string $roomTypeCode, int $roomTier): static
    {
        return $this->state(fn (array $attributes) => [
            'room_type_code' => $roomTypeCode,
            'room_tier' => $roomTier,
        ]);
    }
}
