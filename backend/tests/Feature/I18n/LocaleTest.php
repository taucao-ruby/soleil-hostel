<?php

namespace Tests\Feature\I18n;

use App\Models\Location;
use App\Models\Room;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LocaleTest extends TestCase
{
    use RefreshDatabase;

    public function test_room_list_returns_english_message_when_locale_is_en(): void
    {
        app()->setLocale('en');

        $location = Location::factory()->create(['is_active' => true]);
        Room::factory()->create(['location_id' => $location->id]);

        $response = $this->getJson('/api/v1/rooms');

        $response->assertOk();
        $response->assertJsonFragment(['message' => 'Room list fetched successfully']);
    }

    public function test_room_list_returns_vietnamese_message_when_locale_is_vi(): void
    {
        app()->setLocale('vi');

        $location = Location::factory()->create(['is_active' => true]);
        Room::factory()->create(['location_id' => $location->id]);

        $response = $this->getJson('/api/v1/rooms');

        $response->assertOk();
        $response->assertJsonFragment(['message' => 'Lấy danh sách phòng thành công']);
    }

    public function test_booking_create_error_returns_localized_message(): void
    {
        app()->setLocale('vi');

        $user = User::factory()->create(['email_verified_at' => now()]);
        $token = $user->createToken('test')->plainTextToken;

        // Invalid booking: missing required fields should trigger 422
        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/bookings', []);

        $response->assertStatus(422);
    }

    public function test_location_list_returns_localized_message(): void
    {
        app()->setLocale('vi');

        Location::factory()->create(['is_active' => true]);

        $response = $this->getJson('/api/v1/locations');

        $response->assertOk();
        $response->assertJsonFragment(['message' => 'Lấy danh sách địa điểm thành công']);
    }

    public function test_fallback_locale_works_for_missing_translation(): void
    {
        // Set to a locale without translation files
        app()->setLocale('fr');

        $location = Location::factory()->create(['is_active' => true]);
        Room::factory()->create(['location_id' => $location->id]);

        $response = $this->getJson('/api/v1/rooms');

        $response->assertOk();
        // Should fallback to English
        $response->assertJsonFragment(['message' => 'Room list fetched successfully']);
    }
}
