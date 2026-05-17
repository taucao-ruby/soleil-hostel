<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Room;
use App\Services\RoomAvailabilityService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

final class RoomAvailabilityServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_room_detail_payload_includes_pending_and_confirmed_bookings_only(): void
    {
        Cache::flush();

        $room = Room::factory()->available()->ready()->create([
            'name' => 'Payload Guard Room',
            'price' => 125000,
            'max_guests' => 2,
        ]);

        Booking::factory()->for($room)->pending()->create([
            'check_in' => '2030-01-10',
            'check_out' => '2030-01-12',
        ]);

        Booking::factory()->for($room)->confirmed()->create([
            'check_in' => '2030-01-13',
            'check_out' => '2030-01-15',
        ]);

        Booking::factory()->for($room)->cancelled()->create([
            'check_in' => '2030-01-16',
            'check_out' => '2030-01-18',
        ]);

        $payload = app(RoomAvailabilityService::class)->getRoomDetailWithBookings($room->id);

        $this->assertIsArray($payload);
        $this->assertEqualsCanonicalizing([
            ['check_in' => '2030-01-10', 'check_out' => '2030-01-12'],
            ['check_in' => '2030-01-13', 'check_out' => '2030-01-15'],
        ], $payload['bookings']);
    }
}
