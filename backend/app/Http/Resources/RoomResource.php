<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * RoomResource - API response transformation for Room model.
 *
 * Always includes lock_version to support optimistic locking on the client side.
 * Clients should store lock_version and send it back when updating a room.
 */
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
            
            // ===== OPTIMISTIC LOCKING =====
            // Clients MUST include this value when sending update requests
            // If the version doesn't match, the update will be rejected with 409 Conflict
            'lock_version' => $this->lock_version,
            
            // ===== CONDITIONAL RELATIONSHIPS =====
            // Booking count - always loaded via withCount('activeBookings')
            'active_bookings_count' => $this->active_bookings_count ?? 0,
            
            'created_at' => $this->when($this->created_at !== null, fn() => $this->created_at->toIso8601String()),
            'updated_at' => $this->when($this->updated_at !== null, fn() => $this->updated_at->toIso8601String()),
        ];
    }
}
