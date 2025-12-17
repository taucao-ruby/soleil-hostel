<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * API Resource for User model.
 * 
 * Controls what user data is exposed in API responses.
 * SECURITY: Never expose raw role value - use boolean flags instead.
 * 
 * @mixin \App\Models\User
 */
class UserResource extends JsonResource
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
            'email' => $this->email,
            'email_verified_at' => $this->email_verified_at?->toISOString(),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),

            // ========== RBAC Flags ==========
            // Expose role capabilities as booleans, not raw role value.
            // This prevents enumeration of role names and future-proofs the API.
            'is_admin' => $this->isAdmin(),
            'is_moderator' => $this->isModerator(),

            // Conditional: Only include detailed permissions for the authenticated user
            $this->mergeWhen($this->isCurrentUser($request), [
                'permissions' => [
                    'can_manage_users' => $this->isAdmin(),
                    'can_manage_rooms' => $this->isAdmin(),
                    'can_moderate_content' => $this->isModerator(),
                    'can_view_all_bookings' => $this->isModerator(),
                ],
            ]),

            // Conditional: Include bookings count for moderators viewing users
            $this->mergeWhen($this->shouldIncludeStats($request), [
                'bookings_count' => $this->whenCounted('bookings'),
            ]),
        ];
    }

    /**
     * Check if this resource represents the currently authenticated user.
     */
    private function isCurrentUser(Request $request): bool
    {
        return $request->user()?->id === $this->id;
    }

    /**
     * Check if stats should be included (for admin/moderator views).
     */
    private function shouldIncludeStats(Request $request): bool
    {
        return $request->user()?->isModerator() ?? false;
    }
}
