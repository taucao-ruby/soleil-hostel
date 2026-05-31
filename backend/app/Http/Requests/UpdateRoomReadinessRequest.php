<?php

namespace App\Http\Requests;

use App\Enums\RoomReadinessStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

/**
 * Validates an operational room readiness transition (SH-10 / F-63).
 *
 * Canonical readiness_status only — distinct from the deprecated rooms.status
 * availability semantics. Authorization is enforced by the route `role:moderator`
 * middleware and RoomPolicy::updateReadiness (defense in depth), so this request
 * only validates shape.
 */
class UpdateRoomReadinessRequest extends FormRequest
{
    public function authorize(): bool
    {
        // RBAC is enforced by route middleware + RoomPolicy::updateReadiness.
        return true;
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'readiness_status' => ['required', new Enum(RoomReadinessStatus::class)],
            // Optional optimistic-lock token, consistent with RoomRequest.
            'lock_version' => ['sometimes', 'nullable', 'integer', 'min:1'],
        ];
    }

    public function getLockVersion(): ?int
    {
        $version = $this->validated('lock_version');

        return $version !== null ? (int) $version : null;
    }
}
