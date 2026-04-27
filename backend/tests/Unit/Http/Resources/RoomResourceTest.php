<?php

namespace Tests\Unit\Http\Resources;

use App\Http\Resources\RoomResource;
use App\Models\Room;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class RoomResourceTest extends TestCase
{
    public function test_exposes_public_image_url_from_image_url_column(): void
    {
        Storage::fake('public');

        $room = Room::factory()->make([
            'location_id' => 1,
            'image_url' => 'https://example.test/storage/rooms/deluxe.jpg',
        ]);

        $payload = (new RoomResource($room))->toArray(request());

        $this->assertSame('https://example.test/storage/rooms/deluxe.jpg', $payload['image_url']);
    }

    public function test_returns_null_for_image_url_when_absent(): void
    {
        $room = Room::factory()->make([
            'location_id' => 1,
            'image_url' => null,
        ]);

        $payload = (new RoomResource($room))->toArray(request());

        $this->assertNull($payload['image_url']);
    }

    public function test_does_not_expose_raw_image_path_as_image_url(): void
    {
        Storage::fake('public');

        $room = Room::factory()->make([
            'location_id' => 1,
            'image_url' => null,
        ]);
        $room->setAttribute('image_path', 'rooms/deluxe.jpg');

        $payload = (new RoomResource($room))->toArray(request());

        $this->assertNotSame('rooms/deluxe.jpg', $payload['image_url']);
        $this->assertNull($payload['image_url']);
    }
}
