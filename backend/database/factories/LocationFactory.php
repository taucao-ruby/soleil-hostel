<?php

namespace Database\Factories;

use App\Models\Location;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * LocationFactory
 *
 * Generates test data for Location model.
 * Provides states for common testing scenarios.
 */
class LocationFactory extends Factory
{
    protected $model = Location::class;

    public function definition(): array
    {
        $name = $this->faker->unique()->company() . ' Hostel';

        return [
            'name' => $name,
            'slug' => Str::slug($name),
            'address' => $this->faker->streetAddress(),
            'city' => $this->faker->city(),
            'district' => $this->faker->citySuffix(),
            'ward' => null,
            'postal_code' => $this->faker->postcode(),
            'latitude' => $this->faker->latitude(16.4, 16.6),
            'longitude' => $this->faker->longitude(107.5, 107.7),
            'phone' => $this->faker->phoneNumber(),
            'email' => $this->faker->companyEmail(),
            'description' => $this->faker->paragraph(),
            'amenities' => $this->faker->randomElements(
                ['wifi', 'air_conditioning', 'hot_water', 'breakfast', 'parking', 'pool', 'gym', 'laundry', 'garden'],
                $this->faker->numberBetween(3, 6)
            ),
            'images' => [],
            'is_active' => true,
            'total_rooms' => $this->faker->numberBetween(5, 15),
        ];
    }

    /**
     * State: Inactive location (closed for business).
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    /**
     * State: Location without coordinates.
     */
    public function withoutCoordinates(): static
    {
        return $this->state(fn (array $attributes) => [
            'latitude' => null,
            'longitude' => null,
        ]);
    }

    /**
     * State: Location with specific slug.
     */
    public function withSlug(string $slug): static
    {
        return $this->state(fn (array $attributes) => [
            'slug' => $slug,
        ]);
    }

    /**
     * State: Huế city location.
     */
    public function inHue(): static
    {
        return $this->state(fn (array $attributes) => [
            'city' => 'Thành phố Huế',
            'postal_code' => '530000',
            'latitude' => $this->faker->latitude(16.45, 16.48),
            'longitude' => $this->faker->longitude(107.57, 107.61),
        ]);
    }
}
