<?php

namespace Tests\Feature\Validation;

use App\Models\Location;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Tests that FormRequest validation works correctly for refactored controllers.
 *
 * Covers: M-02 (AdminBookingController), M-04 (LocationController), M-05 (ContactController).
 */
class FormRequestValidationTest extends TestCase
{
    use RefreshDatabase;

    // ========== StoreContactRequest ==========

    public function test_contact_store_requires_name(): void
    {
        $response = $this->postJson('/api/contact', [
            'email' => 'test@example.com',
            'message' => 'Hello',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('name');
    }

    public function test_contact_store_requires_email(): void
    {
        $response = $this->postJson('/api/contact', [
            'name' => 'Test User',
            'message' => 'Hello',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('email');
    }

    public function test_contact_store_requires_message(): void
    {
        $response = $this->postJson('/api/contact', [
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('message');
    }

    public function test_contact_store_rejects_invalid_email(): void
    {
        $response = $this->postJson('/api/contact', [
            'name' => 'Test User',
            'email' => 'not-an-email',
            'message' => 'Hello',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('email');
    }

    public function test_contact_store_accepts_valid_data(): void
    {
        $response = $this->postJson('/api/contact', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'subject' => 'Test Subject',
            'message' => 'Hello, this is a test message.',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true);
    }

    public function test_contact_store_purifies_html(): void
    {
        $response = $this->postJson('/api/contact', [
            'name' => '<script>alert("xss")</script>Test',
            'email' => 'test@example.com',
            'message' => '<img onerror=alert(1) src=x>Hello',
        ]);

        $response->assertStatus(201);

        $this->assertDatabaseMissing('contact_messages', [
            'name' => '<script>alert("xss")</script>Test',
        ]);
    }

    // ========== ShowLocationRequest ==========

    public function test_location_show_requires_check_in_when_check_out_present(): void
    {
        $location = Location::factory()->create(['is_active' => true]);

        // check_in is required_with:check_out — sending check_out without check_in should fail
        $response = $this->getJson("/api/v1/locations/{$location->slug}?check_out=2026-06-05");

        $response->assertStatus(422)
            ->assertJsonValidationErrors('check_in');
    }

    public function test_location_show_rejects_check_out_before_check_in(): void
    {
        $location = Location::factory()->create(['is_active' => true]);

        $response = $this->getJson("/api/v1/locations/{$location->slug}?check_in=2026-06-05&check_out=2026-06-01");

        $response->assertStatus(422)
            ->assertJsonValidationErrors('check_out');
    }

    public function test_location_show_accepts_valid_dates(): void
    {
        $location = Location::factory()->create(['is_active' => true]);

        $response = $this->getJson("/api/v1/locations/{$location->slug}?check_in=2026-06-01&check_out=2026-06-05");

        $response->assertStatus(200);
    }

    // ========== LocationAvailabilityRequest ==========

    public function test_availability_requires_check_in(): void
    {
        $location = Location::factory()->create(['is_active' => true]);

        $response = $this->getJson("/api/v1/locations/{$location->slug}/availability?check_out=2026-06-05");

        $response->assertStatus(422)
            ->assertJsonValidationErrors('check_in');
    }

    public function test_availability_requires_check_out(): void
    {
        $location = Location::factory()->create(['is_active' => true]);

        $response = $this->getJson("/api/v1/locations/{$location->slug}/availability?check_in=2026-06-01");

        $response->assertStatus(422)
            ->assertJsonValidationErrors('check_out');
    }

    public function test_availability_rejects_past_check_in(): void
    {
        $location = Location::factory()->create(['is_active' => true]);

        $response = $this->getJson("/api/v1/locations/{$location->slug}/availability?check_in=2020-01-01&check_out=2020-01-05");

        $response->assertStatus(422)
            ->assertJsonValidationErrors('check_in');
    }

    // ========== BulkRestoreBookingsRequest ==========

    public function test_bulk_restore_requires_ids(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($admin, 'sanctum')
            ->postJson('/api/v1/admin/bookings/restore-bulk', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('ids');
    }

    public function test_bulk_restore_requires_ids_to_be_array(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($admin, 'sanctum')
            ->postJson('/api/v1/admin/bookings/restore-bulk', ['ids' => 'not-array']);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('ids');
    }

    public function test_bulk_restore_requires_non_empty_ids(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($admin, 'sanctum')
            ->postJson('/api/v1/admin/bookings/restore-bulk', ['ids' => []]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('ids');
    }
}
