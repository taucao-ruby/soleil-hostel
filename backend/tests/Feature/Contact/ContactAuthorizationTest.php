<?php

namespace Tests\Feature\Contact;

use App\Enums\UserRole;
use App\Models\ContactMessage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * ContactAuthorizationTest — Defense-in-depth validation for contact admin endpoints.
 *
 * Validates that ContactController enforces moderator+ access via both
 * route middleware (role:moderator) and controller-level Gate::authorize('moderate-content').
 *
 * Covers: G-02 (Phase 1), Phase 3 (moderator activation)
 */
class ContactAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $moderator;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create(['role' => UserRole::ADMIN]);
        $this->moderator = User::factory()->create(['role' => UserRole::MODERATOR]);
        $this->user = User::factory()->create(['role' => UserRole::USER]);
    }

    // ========== GET /api/v1/admin/contact-messages ==========

    public function test_moderator_can_access_contact_index(): void
    {
        ContactMessage::create([
            'name' => 'Test',
            'email' => 'test@example.com',
            'message' => 'Hello',
        ]);

        $response = $this->actingAs($this->moderator, 'sanctum')
            ->getJson('/api/v1/admin/contact-messages');

        $response->assertStatus(200)
            ->assertJsonPath('success', true);
    }

    public function test_user_cannot_access_contact_index(): void
    {
        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/v1/admin/contact-messages');

        $response->assertStatus(403);
    }

    public function test_guest_cannot_access_contact_index(): void
    {
        $response = $this->getJson('/api/v1/admin/contact-messages');

        $response->assertStatus(401);
    }

    public function test_admin_can_access_contact_index(): void
    {
        ContactMessage::create([
            'name' => 'Test',
            'email' => 'test@example.com',
            'message' => 'Hello',
        ]);

        $response = $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/v1/admin/contact-messages');

        $response->assertStatus(200)
            ->assertJsonPath('success', true);
    }

    // ========== PATCH /api/v1/admin/contact-messages/{id}/read ==========

    public function test_moderator_can_mark_contact_as_read(): void
    {
        $msg = ContactMessage::create([
            'name' => 'Test',
            'email' => 'test@example.com',
            'message' => 'Hello',
        ]);

        $response = $this->actingAs($this->moderator, 'sanctum')
            ->patchJson("/api/v1/admin/contact-messages/{$msg->id}/read");

        $response->assertStatus(200)
            ->assertJsonPath('success', true);
    }

    public function test_user_cannot_mark_contact_as_read(): void
    {
        $msg = ContactMessage::create([
            'name' => 'Test',
            'email' => 'test@example.com',
            'message' => 'Hello',
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->patchJson("/api/v1/admin/contact-messages/{$msg->id}/read");

        $response->assertStatus(403);
    }

    public function test_admin_can_mark_contact_as_read(): void
    {
        $msg = ContactMessage::create([
            'name' => 'Test',
            'email' => 'test@example.com',
            'message' => 'Hello',
        ]);

        $response = $this->actingAs($this->admin, 'sanctum')
            ->patchJson("/api/v1/admin/contact-messages/{$msg->id}/read");

        $response->assertStatus(200)
            ->assertJsonPath('success', true);
    }
}
