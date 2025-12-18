<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RoomResource extends JsonResource
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
            'description' => $this->description,
            'price' => (float) $this->price,
            'max_guests' => $this->max_guests,
            'status' => $this->status,
            
            // ===== CONDITIONAL RELATIONSHIPS =====
            // Booking count - always loaded via withCount('activeBookings')
            'active_bookings_count' => $this->active_bookings_count ?? 0,
            
            'created_at' => $this->when($this->created_at !== null, fn() => $this->created_at->toIso8601String()),
            'updated_at' => $this->when($this->updated_at !== null, fn() => $this->updated_at->toIso8601String()),
        ];
    }
}
