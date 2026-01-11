<?php

namespace App\Http\Resources;

use App\Enums\BookingStatus;
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
            'status' => $this->status instanceof BookingStatus 
                ? $this->status->value 
                : $this->status,
            'status_label' => $this->status instanceof BookingStatus 
                ? $this->status->label() 
                : null,
            'nights' => $this->nights,
            
            // ===== PAYMENT INFO =====
            'amount' => $this->when($this->amount !== null, $this->amount),
            'amount_formatted' => $this->when(
                $this->amount !== null, 
                fn() => '$' . number_format($this->amount / 100, 2)
            ),
            
            // ===== REFUND INFO (visible when cancelled with refund) =====
            'refund_amount' => $this->when(
                $this->status === BookingStatus::CANCELLED && $this->refund_amount,
                $this->refund_amount
            ),
            'refund_amount_formatted' => $this->when(
                $this->status === BookingStatus::CANCELLED && $this->refund_amount,
                fn() => '$' . number_format($this->refund_amount / 100, 2)
            ),
            'refund_status' => $this->when(
                $this->refund_status !== null,
                $this->refund_status
            ),
            
            // ===== CANCELLATION INFO =====
            'cancelled_at' => $this->when(
                $this->cancelled_at !== null,
                fn() => $this->cancelled_at?->toIso8601String()
            ),
            'cancelled_by' => $this->whenLoaded('cancelledBy', fn() => [
                'id' => $this->cancelledBy->id,
                'name' => $this->cancelledBy->name,
            ]),
            
            // ===== REFUND ELIGIBILITY (for pending/confirmed bookings) =====
            'refund_percentage' => $this->when(
                $this->status instanceof BookingStatus && $this->status->isCancellable(),
                fn() => $this->getRefundPercentage()
            ),
            
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
