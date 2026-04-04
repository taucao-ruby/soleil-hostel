<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * LocationResource - API response transformation for Location model.
 *
 * Includes nested address structure, coordinates for map integration,
 * and conditionally loaded room data.
 */
class LocationResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'address' => [
                'full' => $this->full_address,
                'street' => $this->address,
                'ward' => $this->ward,
                'district' => $this->district,
                'city' => $this->city,
                'postal_code' => $this->postal_code,
            ],
            'coordinates' => $this->coordinates,
            'contact' => [
                'phone' => $this->phone,
                'email' => $this->email,
            ],
            'description' => $this->description,
            'amenities' => $this->amenities ?? [],
            'images' => $this->images ?? [],
            'stats' => [
                'total_rooms' => $this->rooms_count ?? $this->total_rooms,
                'available_rooms' => $this->when(
                    isset($this->available_rooms_count),
                    fn () => $this->available_rooms_count
                ),
                'rooms_count' => $this->when(
                    isset($this->rooms_count),
                    fn () => $this->rooms_count
                ),
            ],
            'rooms' => RoomResource::collection($this->whenLoaded('rooms')),
            'is_active' => $this->is_active,
            'lock_version' => $this->lock_version,
            'created_at' => $this->when(
                $this->created_at !== null,
                fn () => $this->created_at->toIso8601String()
            ),
            'updated_at' => $this->when(
                $this->updated_at !== null,
                fn () => $this->updated_at->toIso8601String()
            ),
        ];
    }
}
