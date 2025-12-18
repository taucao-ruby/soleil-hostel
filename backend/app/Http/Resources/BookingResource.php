<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BookingResource extends JsonResource
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
            'room_id' => $this->room_id,
            'user_id' => $this->user_id,
            'check_in' => $this->check_in->format('Y-m-d'),
            'check_out' => $this->check_out->format('Y-m-d'),
            'guest_name' => $this->guest_name,
            'guest_email' => $this->guest_email,
            'status' => $this->status,
            'nights' => $this->nights,
            
            // ===== CONDITIONAL RELATIONSHIPS =====
            // Only include relationships if they were eager-loaded
            'room' => $this->whenLoaded('room', fn() => new RoomResource($this->room)),
            'user' => $this->whenLoaded('user', fn() => new UserResource($this->user)),
            
            'created_at' => $this->created_at->toIso8601String(),
            'updated_at' => $this->updated_at->toIso8601String(),
            
            // ===== SOFT DELETE INFO (Admin views only) =====
            // These fields are only present for trashed bookings
            'is_trashed' => $this->when($this->trashed(), true),
            'deleted_at' => $this->when($this->trashed(), fn() => $this->deleted_at?->toIso8601String()),
            'deleted_by' => $this->whenLoaded('deletedBy', fn() => [
                'id' => $this->deletedBy->id,
                'name' => $this->deletedBy->name,
                'email' => $this->deletedBy->email,
            ]),
        ];
    }
}
