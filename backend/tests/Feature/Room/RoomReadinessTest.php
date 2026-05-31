<?php

declare(strict_types=1);

namespace Tests\Feature\Room;

use App\Enums\RoomReadinessStatus;
use App\Enums\UserRole;
use App\Models\Location;
use App\Models\Room;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * SH-10 / F-63: canonical room readiness endpoint.
 *
 * PATCH /api/v1/rooms/{room}/readiness — operates on the canonical
 * readiness_status (NOT the deprecated rooms.status), is authorized for
 * moderator+ (front-desk operators), and validates the readiness enum.
 */
class RoomReadinessTest extends TestCase
{
    use RefreshDatabase;

    private Room $room;

    protected function setUp(): void
    {
        parent::setUp();

        $location = Location::factory()->create();
        $this->room = Room::factory()->create([
            'location_id' => $location->id,
            'readiness_status' => RoomReadinessStatus::READY->value,
        ]);
    }

    private function url(): string
    {
        return "/api/v1/rooms/{$this->room->id}/readiness";
    }

    public function test_guest_cannot_update_readiness(): void
    {
        $this->patchJson($this->url(), ['readiness_status' => 'occupied'])
            ->assertStatus(401);
    }

    public function test_regular_user_cannot_update_readiness(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => UserRole::USER]));

        $this->patchJson($this->url(), ['readiness_status' => 'occupied'])
            ->assertStatus(403);
    }

    public function test_moderator_can_update_readiness(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => UserRole::MODERATOR]));

        $this->patchJson($this->url(), ['readiness_status' => 'occupied'])
            ->assertStatus(200)
            ->assertJsonPath('data.readiness_status', 'occupied');

        $this->assertSame(RoomReadinessStatus::OCCUPIED, $this->room->fresh()->readiness_status);
    }

    public function test_admin_can_update_readiness(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => UserRole::ADMIN]));

        $this->patchJson($this->url(), ['readiness_status' => 'cleaning'])
            ->assertStatus(200)
            ->assertJsonPath('data.readiness_status', 'cleaning');

        $this->assertSame(RoomReadinessStatus::CLEANING, $this->room->fresh()->readiness_status);
    }

    public function test_invalid_readiness_status_is_rejected(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => UserRole::MODERATOR]));

        $this->patchJson($this->url(), ['readiness_status' => 'teleporting'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('readiness_status');
    }

    public function test_readiness_status_is_required(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => UserRole::MODERATOR]));

        $this->patchJson($this->url(), [])
            ->assertStatus(422)
            ->assertJsonValidationErrors('readiness_status');
    }

    public function test_readiness_update_stamps_audit_columns(): void
    {
        $moderator = User::factory()->create(['role' => UserRole::MODERATOR]);
        Sanctum::actingAs($moderator);

        $this->patchJson($this->url(), ['readiness_status' => 'inspected'])
            ->assertStatus(200);

        $fresh = $this->room->fresh();
        $this->assertSame(RoomReadinessStatus::INSPECTED, $fresh->readiness_status);
        $this->assertNotNull($fresh->readiness_updated_at);
        $this->assertSame($moderator->id, $fresh->readiness_updated_by);
    }

    public function test_readiness_endpoint_does_not_touch_deprecated_status(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => UserRole::MODERATOR]));

        $originalStatus = $this->room->status;

        $this->patchJson($this->url(), ['readiness_status' => 'dirty'])
            ->assertStatus(200);

        // Availability status is untouched; only readiness_status changes.
        $this->assertSame($originalStatus, $this->room->fresh()->status);
    }
}
