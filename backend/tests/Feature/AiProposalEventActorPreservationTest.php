<?php

namespace Tests\Feature;

use App\Models\AiProposalEvent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Batch 4 / 3F — ai_proposal_events FK relaxation + actor denormalisation.
 *
 * Contract:
 *   1. user_id FK is ON DELETE SET NULL (was CASCADE).
 *   2. actor_email / actor_role / actor_display_name are populated at write time
 *      and survive user deletion so the audit trail remains attributable.
 */
class AiProposalEventActorPreservationTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_delete_preserves_proposal_event_with_actor_columns(): void
    {
        $user = User::factory()->create([
            'email' => 'proposer@example.com',
            'name' => 'Alice Proposer',
        ]);

        // Write a proposal event the way ProposalConfirmationController::recordEvent does.
        $event = AiProposalEvent::create([
            'user_id' => $user->id,
            'actor_email' => $user->email,
            'actor_role' => $user->role instanceof \BackedEnum ? $user->role->value : $user->role,
            'actor_display_name' => $user->name,
            'proposal_hash' => str_repeat('a', 64),
            'action_type' => 'suggest_booking',
            'user_decision' => 'confirmed',
            'downstream_result' => 'booking_created:42',
        ]);

        $this->assertNotNull($event->user_id);
        $this->assertSame('proposer@example.com', $event->actor_email);
        $this->assertSame('Alice Proposer', $event->actor_display_name);

        // Delete the user — Cascade would remove the event row, SET NULL preserves it.
        $user->delete();

        $reloaded = AiProposalEvent::find($event->id);
        $this->assertNotNull($reloaded, 'Event row must survive user deletion (FK is ON DELETE SET NULL).');
        $this->assertNull($reloaded->user_id, 'user_id must be nulled out.');
        $this->assertSame('proposer@example.com', $reloaded->actor_email, 'actor_email must persist.');
        $this->assertSame('Alice Proposer', $reloaded->actor_display_name, 'actor_display_name must persist.');
    }

    public function test_fk_constraint_is_set_null_not_cascade(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            $this->markTestSkipped('FK introspection assertion is Postgres-specific.');
        }

        $row = DB::selectOne("
            SELECT confdeltype
            FROM pg_constraint
            WHERE conname = 'ai_proposal_events_user_id_foreign'
        ");

        $this->assertNotNull($row, 'FK constraint ai_proposal_events_user_id_foreign must exist.');
        // Postgres confdeltype: 'n' = SET NULL, 'c' = CASCADE.
        $this->assertSame('n', $row->confdeltype, 'user_id FK must be ON DELETE SET NULL.');
    }
}
