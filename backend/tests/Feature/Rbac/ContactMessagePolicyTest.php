<?php

namespace Tests\Feature\Rbac;

use App\Models\ContactMessage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

class ContactMessagePolicyTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_mark_contact_message_read(): void
    {
        $admin = User::factory()->admin()->create();

        $this->assertTrue(
            Gate::forUser($admin)->allows('markRead', ContactMessage::class)
        );
    }

    public function test_moderator_cannot_mark_contact_message_read(): void
    {
        $moderator = User::factory()->moderator()->create();

        $this->assertFalse(
            Gate::forUser($moderator)->allows('markRead', ContactMessage::class)
        );
    }

    public function test_user_cannot_mark_contact_message_read(): void
    {
        $user = User::factory()->user()->create();

        $this->assertFalse(
            Gate::forUser($user)->allows('markRead', ContactMessage::class)
        );
    }

    public function test_admin_can_draft_admin_message_for_contact_message(): void
    {
        $admin = User::factory()->admin()->create();

        $this->assertTrue(
            Gate::forUser($admin)->allows('draftAdminMessage', ContactMessage::class)
        );
    }

    public function test_moderator_cannot_draft_admin_message_for_contact_message(): void
    {
        $moderator = User::factory()->moderator()->create();

        $this->assertFalse(
            Gate::forUser($moderator)->allows('draftAdminMessage', ContactMessage::class)
        );
    }

    public function test_user_cannot_draft_admin_message_for_contact_message(): void
    {
        $user = User::factory()->user()->create();

        $this->assertFalse(
            Gate::forUser($user)->allows('draftAdminMessage', ContactMessage::class)
        );
    }
}
