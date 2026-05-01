<?php

namespace Tests\Feature\Audit;

use App\Enums\BookingStatus;
use App\Models\AdminAuditLog;
use App\Models\Booking;
use App\Models\Room;
use App\Models\User;
use App\Services\AdminAuditService;
use App\Services\CancellationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ActorSnapshotTest extends TestCase
{
    use RefreshDatabase;

    public function test_booking_cancellation_actor_snapshot_survives_actor_deletion(): void
    {
        $actor = User::factory()->admin()->create([
            'name' => 'Alice Admin',
            'email' => 'alice.admin@example.com',
        ]);
        $guest = User::factory()->user()->create();
        $room = Room::factory()->create();
        $booking = Booking::factory()
            ->for($guest)
            ->for($room)
            ->confirmed()
            ->create([
                'check_in' => now()->addDays(10),
                'check_out' => now()->addDays(12),
                'payment_intent_id' => null,
                'amount' => null,
            ]);

        app(CancellationService::class)->cancel($booking, $actor);

        $actor->delete();

        $reloaded = Booking::findOrFail($booking->id);
        $this->assertSame(BookingStatus::CANCELLED, $reloaded->status);
        $this->assertNull($reloaded->cancelled_by);
        $this->assertSame('alice.admin@example.com', $reloaded->cancelled_by_email);
        $this->assertSame('admin', $reloaded->cancelled_by_role);
        $this->assertSame('Alice Admin', $reloaded->cancelled_by_display);
    }

    public function test_admin_audit_actor_snapshot_survives_actor_deletion(): void
    {
        $actor = User::factory()->admin()->create([
            'name' => 'Audrey Auditor',
            'email' => 'audrey@example.com',
        ]);

        $this->actingAs($actor);

        $log = app(AdminAuditService::class)->log(
            'room.delete',
            'room',
            123,
            ['reason' => 'snapshot regression test']
        );

        $actor->delete();

        $reloaded = AdminAuditLog::findOrFail($log->id);
        $this->assertNull($reloaded->actor_id);
        $this->assertSame('audrey@example.com', $reloaded->actor_email);
        $this->assertSame('admin', $reloaded->actor_role);
        $this->assertSame('Audrey Auditor', $reloaded->actor_display_name);
    }
}
