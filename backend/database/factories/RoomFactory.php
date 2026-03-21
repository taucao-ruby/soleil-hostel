<?php

namespace Database\Factories;

use App\Enums\RoomTypeCode;
use App\Models\Location;
use App\Models\Room;
use Illuminate\Database\Eloquent\Factories\Factory;

class RoomFactory extends Factory
{
    protected $model = Room::class;

    public function definition(): array
    {
        $typeCode = $this->faker->randomElement(RoomTypeCode::cases());

        return [
            'location_id' => Location::factory(),
            'name' => $this->faker->word.' Room',
            'room_number' => (string) $this->faker->unique()->numberBetween(100, 999),
            'description' => $this->faker->sentence(10),
            'price' => $this->faker->randomFloat(2, 20, 200),
            'max_guests' => $this->faker->numberBetween(1, 8),
            'status' => 'available',
            'room_type_code' => $typeCode,
            'room_tier' => $typeCode->defaultTier(),
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
     * State: Booked room.
     */
    public function booked(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'booked',
        ]);
    }

    /**
     * State: Maintenance room.
     */
    public function maintenance(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'maintenance',
        ]);
    }

    /**
     * State: Room with specific type code and derived tier.
     */
    public function ofType(RoomTypeCode $typeCode, ?int $tier = null): static
    {
        return $this->state(fn (array $attributes) => [
            'room_type_code' => $typeCode,
            'room_tier' => $tier ?? $typeCode->defaultTier(),
        ]);
    }

    /**
     * State: Dormitory room (tier 1).
     */
    public function dormitory(): static
    {
        return $this->ofType(RoomTypeCode::DORMITORY);
    }

    /**
     * State: Private suite (tier 3).
     */
    public function privateSuite(): static
    {
        return $this->ofType(RoomTypeCode::PRIVATE_SUITE);
    }
}
