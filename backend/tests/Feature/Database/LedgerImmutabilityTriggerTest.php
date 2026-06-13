<?php

namespace Tests\Feature\Database;

use App\Models\AdminAuditLog;
use App\Models\AiProposalEvent;
use App\Models\Booking;
use App\Models\DepositEvent;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Ledger Immutability Trigger Tests — storage-layer append-only enforcement.
 *
 * Covers migration 2026_06_12_000002_add_ledger_immutability_triggers.php per
 * docs/DECISION_LEDGER_IMMUTABILITY_FK.md (D1, F-83/F-90):
 *
 * - every direct UPDATE/DELETE on deposit_events, admin_audit_logs and
 *   ai_proposal_events is rejected with SQLSTATE P0001, regardless of code path;
 * - the single sanctioned mutation — ONLY the actor-reference column changes,
 *   and its new value is NULL — succeeds (this is the row image
 *   ON DELETE SET NULL produces; user-deletion end-to-end coverage lives in
 *   FkDeletePolicyTest);
 * - actor reattribution and "actor → NULL plus anything else" are rejected;
 * - a predicate guard pins the live trigger/function definitions (F-80/C-1
 *   pattern: assert the definition text, not mere existence).
 *
 * These tests require PostgreSQL (the trigger migration is driver-guarded).
 */
class LedgerImmutabilityTriggerTest extends TestCase
{
    use RefreshDatabase;

    private function isPgsql(): bool
    {
        return DB::getDriverName() === 'pgsql';
    }

    private function makeDepositEvent(?int $actorId = null): DepositEvent
    {
        $booking = Booking::factory()->cancelled()->create();

        return DepositEvent::create([
            'booking_id' => $booking->id,
            'from_status' => 'collected',
            'to_status' => 'refunded',
            'refund_percent' => 100,
            'refund_amount' => 10_000,
            'reason' => 'cancelled_within_full_refund_window',
            'actor_id' => $actorId,
        ]);
    }

    private function makeAuditLog(?int $actorId = null): AdminAuditLog
    {
        return AdminAuditLog::create([
            'actor_id' => $actorId,
            'action' => 'booking.force_delete',
            'resource_type' => 'booking',
            'resource_id' => 1,
        ]);
    }

    private function makeProposalEvent(?int $userId = null): AiProposalEvent
    {
        return AiProposalEvent::create([
            'user_id' => $userId,
            'proposal_hash' => str_repeat('a', 64),
            'action_type' => 'cancel_booking',
            'user_decision' => 'confirmed',
        ]);
    }

    /**
     * Run $mutation inside a savepoint (so the expected error does not abort
     * RefreshDatabase's outer transaction) and assert it is rejected by the
     * append-only trigger with SQLSTATE P0001.
     */
    private function assertRejectedByTrigger(callable $mutation, string $table): void
    {
        try {
            DB::transaction(fn () => $mutation());

            $this->fail("Mutation on {$table} was accepted — the append-only trigger did not fire");
        } catch (QueryException $e) {
            $sqlState = $e->errorInfo[0] ?? (string) $e->getCode();

            $this->assertSame(
                'P0001',
                $sqlState,
                'Expected SQLSTATE P0001 from the append-only trigger, got '.$sqlState.': '.$e->getMessage()
            );
            $this->assertStringContainsString("{$table} is append-only", $e->getMessage());
        }
    }

    // ===== direct UPDATE rejected on each ledger =====

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_direct_update_rejected_on_deposit_events(): void
    {
        if (! $this->isPgsql()) {
            $this->markTestSkipped('Append-only triggers require PostgreSQL');
        }

        $event = $this->makeDepositEvent();

        $this->assertRejectedByTrigger(
            fn () => DB::table('deposit_events')->where('id', $event->id)->update(['reason' => 'tampered']),
            'deposit_events'
        );

        $this->assertDatabaseHas('deposit_events', ['id' => $event->id, 'reason' => $event->reason]);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_direct_update_rejected_on_admin_audit_logs(): void
    {
        if (! $this->isPgsql()) {
            $this->markTestSkipped('Append-only triggers require PostgreSQL');
        }

        $log = $this->makeAuditLog();

        $this->assertRejectedByTrigger(
            fn () => DB::table('admin_audit_logs')->where('id', $log->id)->update(['action' => 'tampered']),
            'admin_audit_logs'
        );

        $this->assertDatabaseHas('admin_audit_logs', ['id' => $log->id, 'action' => 'booking.force_delete']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_direct_update_rejected_on_ai_proposal_events(): void
    {
        if (! $this->isPgsql()) {
            $this->markTestSkipped('Append-only triggers require PostgreSQL');
        }

        $event = $this->makeProposalEvent();

        $this->assertRejectedByTrigger(
            fn () => DB::table('ai_proposal_events')->where('id', $event->id)->update(['user_decision' => 'declined']),
            'ai_proposal_events'
        );

        $this->assertDatabaseHas('ai_proposal_events', ['id' => $event->id, 'user_decision' => 'confirmed']);
    }

    // ===== direct DELETE rejected on each ledger =====

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_direct_delete_rejected_on_deposit_events(): void
    {
        if (! $this->isPgsql()) {
            $this->markTestSkipped('Append-only triggers require PostgreSQL');
        }

        $event = $this->makeDepositEvent();

        $this->assertRejectedByTrigger(
            fn () => DB::table('deposit_events')->where('id', $event->id)->delete(),
            'deposit_events'
        );

        $this->assertDatabaseHas('deposit_events', ['id' => $event->id]);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_direct_delete_rejected_on_admin_audit_logs(): void
    {
        if (! $this->isPgsql()) {
            $this->markTestSkipped('Append-only triggers require PostgreSQL');
        }

        $log = $this->makeAuditLog();

        $this->assertRejectedByTrigger(
            fn () => DB::table('admin_audit_logs')->where('id', $log->id)->delete(),
            'admin_audit_logs'
        );

        $this->assertDatabaseHas('admin_audit_logs', ['id' => $log->id]);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_direct_delete_rejected_on_ai_proposal_events(): void
    {
        if (! $this->isPgsql()) {
            $this->markTestSkipped('Append-only triggers require PostgreSQL');
        }

        $event = $this->makeProposalEvent();

        $this->assertRejectedByTrigger(
            fn () => DB::table('ai_proposal_events')->where('id', $event->id)->delete(),
            'ai_proposal_events'
        );

        $this->assertDatabaseHas('ai_proposal_events', ['id' => $event->id]);
    }

    // ===== the sanctioned mutation (actor column → NULL, nothing else) succeeds =====

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_actor_id_to_null_succeeds_on_deposit_events(): void
    {
        if (! $this->isPgsql()) {
            $this->markTestSkipped('Append-only triggers require PostgreSQL');
        }

        $actor = User::factory()->create();
        $event = $this->makeDepositEvent($actor->id);

        $updated = DB::table('deposit_events')->where('id', $event->id)->update(['actor_id' => null]);

        $this->assertSame(1, $updated);
        $this->assertDatabaseHas('deposit_events', ['id' => $event->id, 'actor_id' => null]);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_actor_id_to_null_succeeds_on_admin_audit_logs(): void
    {
        if (! $this->isPgsql()) {
            $this->markTestSkipped('Append-only triggers require PostgreSQL');
        }

        $actor = User::factory()->create();
        $log = $this->makeAuditLog($actor->id);

        $updated = DB::table('admin_audit_logs')->where('id', $log->id)->update(['actor_id' => null]);

        $this->assertSame(1, $updated);
        $this->assertDatabaseHas('admin_audit_logs', ['id' => $log->id, 'actor_id' => null]);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_user_id_to_null_succeeds_on_ai_proposal_events(): void
    {
        if (! $this->isPgsql()) {
            $this->markTestSkipped('Append-only triggers require PostgreSQL');
        }

        $actor = User::factory()->create();
        $event = $this->makeProposalEvent($actor->id);

        // Raw builder update: only user_id changes (Eloquent would also bump
        // updated_at, which the trigger correctly rejects as a second column).
        $updated = DB::table('ai_proposal_events')->where('id', $event->id)->update(['user_id' => null]);

        $this->assertSame(1, $updated);
        $this->assertDatabaseHas('ai_proposal_events', ['id' => $event->id, 'user_id' => null]);
    }

    // ===== anything beyond the sanctioned shape is rejected =====

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_actor_null_alongside_other_column_rejected_on_deposit_events(): void
    {
        if (! $this->isPgsql()) {
            $this->markTestSkipped('Append-only triggers require PostgreSQL');
        }

        $actor = User::factory()->create();
        $event = $this->makeDepositEvent($actor->id);

        $this->assertRejectedByTrigger(
            fn () => DB::table('deposit_events')->where('id', $event->id)
                ->update(['actor_id' => null, 'reason' => 'tampered']),
            'deposit_events'
        );

        $this->assertDatabaseHas('deposit_events', ['id' => $event->id, 'actor_id' => $actor->id]);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_actor_null_alongside_other_column_rejected_on_admin_audit_logs(): void
    {
        if (! $this->isPgsql()) {
            $this->markTestSkipped('Append-only triggers require PostgreSQL');
        }

        $actor = User::factory()->create();
        $log = $this->makeAuditLog($actor->id);

        $this->assertRejectedByTrigger(
            fn () => DB::table('admin_audit_logs')->where('id', $log->id)
                ->update(['actor_id' => null, 'ip_address' => '10.0.0.1']),
            'admin_audit_logs'
        );

        $this->assertDatabaseHas('admin_audit_logs', ['id' => $log->id, 'actor_id' => $actor->id]);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_user_null_alongside_other_column_rejected_on_ai_proposal_events(): void
    {
        if (! $this->isPgsql()) {
            $this->markTestSkipped('Append-only triggers require PostgreSQL');
        }

        $actor = User::factory()->create();
        $event = $this->makeProposalEvent($actor->id);

        $this->assertRejectedByTrigger(
            fn () => DB::table('ai_proposal_events')->where('id', $event->id)
                ->update(['user_id' => null, 'downstream_result' => 'tampered']),
            'ai_proposal_events'
        );

        $this->assertDatabaseHas('ai_proposal_events', ['id' => $event->id, 'user_id' => $actor->id]);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_actor_reattribution_rejected_on_deposit_events(): void
    {
        if (! $this->isPgsql()) {
            $this->markTestSkipped('Append-only triggers require PostgreSQL');
        }

        $actor = User::factory()->create();
        $other = User::factory()->create();
        $event = $this->makeDepositEvent($actor->id);

        // C-2 flag 2 (memo): the jsonb diff alone would admit re-pointing the
        // actor; the NEW.<col> IS NULL term must reject a non-NULL target.
        $this->assertRejectedByTrigger(
            fn () => DB::table('deposit_events')->where('id', $event->id)->update(['actor_id' => $other->id]),
            'deposit_events'
        );

        $this->assertDatabaseHas('deposit_events', ['id' => $event->id, 'actor_id' => $actor->id]);
    }

    // ===== predicate guard (F-80/C-1 pattern: pin definitions, not existence) =====

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_live_trigger_and_function_definitions_match_decision(): void
    {
        if (! $this->isPgsql()) {
            $this->markTestSkipped('Append-only triggers require PostgreSQL');
        }

        $expected = [
            'deposit_events' => ['trigger' => 'trg_deposit_events_append_only', 'actor_col' => 'actor_id'],
            'admin_audit_logs' => ['trigger' => 'trg_admin_audit_logs_append_only', 'actor_col' => 'actor_id'],
            'ai_proposal_events' => ['trigger' => 'trg_ai_proposal_events_append_only', 'actor_col' => 'user_id'],
        ];

        foreach ($expected as $table => $config) {
            $row = DB::selectOne(
                'SELECT pg_get_triggerdef(t.oid) AS def
                 FROM pg_trigger t
                 WHERE t.tgrelid = ?::regclass AND t.tgname = ? AND NOT t.tgisinternal',
                [$table, $config['trigger']]
            );

            $this->assertNotNull($row, "Trigger {$config['trigger']} is missing on {$table}");
            // pg_get_triggerdef canonicalizes the event list as DELETE OR UPDATE.
            $this->assertStringContainsString('BEFORE DELETE OR UPDATE', $row->def);
            $this->assertStringContainsString('FOR EACH ROW', $row->def);
            $this->assertStringContainsString(
                "enforce_ledger_append_only('{$config['actor_col']}')",
                $row->def,
                "Trigger on {$table} must pass '{$config['actor_col']}' as the sanctioned actor column"
            );
        }

        $fn = DB::selectOne(
            "SELECT pg_get_functiondef(oid) AS def FROM pg_proc WHERE proname = 'enforce_ledger_append_only'"
        );

        $this->assertNotNull($fn, 'enforce_ledger_append_only() is missing');
        $this->assertStringContainsString('(to_jsonb(NEW) ->> actor_col) IS NULL', $fn->def);
        $this->assertStringContainsString('(to_jsonb(NEW) - actor_col) = (to_jsonb(OLD) - actor_col)', $fn->def);
    }
}
