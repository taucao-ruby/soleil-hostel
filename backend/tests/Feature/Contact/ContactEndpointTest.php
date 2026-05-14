<?php

namespace Tests\Feature\Contact;

use App\Models\ContactMessage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Feature tests for ContactController endpoints (H-05).
 */
class ContactEndpointTest extends TestCase
{
    use RefreshDatabase;

    // ========== POST /api/contact ==========

    public function test_contact_store_creates_message(): void
    {
        $response = $this->postJson('/api/contact', [
            'name' => 'Nguyen Van A',
            'email' => 'test@example.com',
            'subject' => 'Test Subject',
            'message' => 'This is a test message.',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true);

        $this->assertDatabaseHas('contact_messages', [
            'email' => 'test@example.com',
            'subject' => 'Test Subject',
        ]);
    }

    public function test_contact_store_without_subject(): void
    {
        $response = $this->postJson('/api/contact', [
            'name' => 'Test User',
            'email' => 'user@example.com',
            'message' => 'Message without subject.',
        ]);

        $response->assertStatus(201);
    }

    public function test_contact_store_rejects_long_message(): void
    {
        $response = $this->postJson('/api/contact', [
            'name' => 'Test User',
            'email' => 'user@example.com',
            'message' => str_repeat('x', 5001),
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('message');
    }

    public function test_contact_store_purifies_xss(): void
    {
        $this->postJson('/api/contact', [
            'name' => '<script>alert(1)</script>Clean Name',
            'email' => 'user@example.com',
            'message' => '<img onerror=alert(1) src=x>Safe message',
        ]);

        $message = ContactMessage::latest()->first();
        $this->assertNotNull($message);
        $this->assertStringNotContainsString('<script>', $message->name);
        $this->assertStringNotContainsString('onerror', $message->message);
    }

    // ========== GET /api/v1/admin/contact-messages ==========

    public function test_contact_index_requires_admin(): void
    {
        $response = $this->getJson('/api/v1/admin/contact-messages');

        $response->assertStatus(401);
    }

    public function test_contact_index_forbidden_for_regular_user(): void
    {
        $user = User::factory()->create(['role' => 'user']);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/admin/contact-messages');

        $response->assertStatus(403);
    }

    public function test_contact_index_returns_messages_for_admin(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        ContactMessage::create([
            'name' => 'Test',
            'email' => 'test@example.com',
            'message' => 'Hello',
        ]);

        $response = $this->actingAs($admin, 'sanctum')
            ->getJson('/api/v1/admin/contact-messages');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.per_page', 15);
    }

    public function test_contact_index_accepts_per_page_fifty_for_admin(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($admin, 'sanctum')
            ->getJson('/api/v1/admin/contact-messages?per_page=50');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.per_page', 50);
    }

    public function test_contact_index_rejects_oversized_per_page_for_admin(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin, 'sanctum')
            ->getJson('/api/v1/admin/contact-messages?per_page=1000000')
            ->assertStatus(422)
            ->assertJsonValidationErrors('per_page');
    }

    public function test_contact_index_rejects_zero_per_page_for_admin(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin, 'sanctum')
            ->getJson('/api/v1/admin/contact-messages?per_page=0')
            ->assertStatus(422)
            ->assertJsonValidationErrors('per_page');
    }

    public function test_contact_index_rejects_negative_per_page_for_admin(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin, 'sanctum')
            ->getJson('/api/v1/admin/contact-messages?per_page=-1')
            ->assertStatus(422)
            ->assertJsonValidationErrors('per_page');
    }

    public function test_contact_index_rejects_non_integer_per_page_for_admin(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin, 'sanctum')
            ->getJson('/api/v1/admin/contact-messages?per_page=abc')
            ->assertStatus(422)
            ->assertJsonValidationErrors('per_page');
    }

    // ========== PATCH /api/v1/admin/contact-messages/{id}/read ==========

    public function test_mark_as_read_requires_admin(): void
    {
        $msg = ContactMessage::create([
            'name' => 'Test',
            'email' => 'test@example.com',
            'message' => 'Hello',
        ]);

        $response = $this->patchJson("/api/v1/admin/contact-messages/{$msg->id}/read");

        $response->assertStatus(401);
    }

    public function test_mark_as_read_works_for_admin(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $msg = ContactMessage::create([
            'name' => 'Test',
            'email' => 'test@example.com',
            'message' => 'Hello',
        ]);

        $response = $this->actingAs($admin, 'sanctum')
            ->patchJson("/api/v1/admin/contact-messages/{$msg->id}/read");

        $response->assertStatus(200)
            ->assertJsonPath('success', true);
    }
}
