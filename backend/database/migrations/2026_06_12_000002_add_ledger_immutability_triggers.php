<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * F-83/F-90: the three audit ledgers (deposit_events, admin_audit_logs,
 * ai_proposal_events) were append-only by convention only — no DB-level
 * guard, and the runtime role has full DML.
 *
 * Decision memo docs/DECISION_LEDGER_IMMUTABILITY_FK.md (D1, accepted
 * 2026-06-12): one shared trigger function enforces append-only with exactly
 * one sanctioned mutation — the actor-reference column (TG_ARGV[0]) may go to
 * NULL and nothing else may change. That is precisely the row image
 * ON DELETE SET NULL produces, so hard-deleting a user still works
 * end-to-end; every other UPDATE and ALL deletes raise SQLSTATE P0001.
 *
 * The actor column differs per ledger: actor_id on deposit_events and
 * admin_audit_logs, user_id on ai_proposal_events (C-2 flag 1 in the memo).
 *
 * Postgres-only by design; SQLite dev runs keep only the Eloquent-layer
 * guards (DepositEvent::booted), consistent with every constraint migration.
 */
return new class extends Migration
{
    /** @var array<string, array{trigger: string, actor_col: string}> */
    private const LEDGERS = [
        'deposit_events' => ['trigger' => 'trg_deposit_events_append_only', 'actor_col' => 'actor_id'],
        'admin_audit_logs' => ['trigger' => 'trg_admin_audit_logs_append_only', 'actor_col' => 'actor_id'],
        'ai_proposal_events' => ['trigger' => 'trg_ai_proposal_events_append_only', 'actor_col' => 'user_id'],
    ];

    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement(<<<'SQL'
            CREATE OR REPLACE FUNCTION enforce_ledger_append_only() RETURNS trigger
            LANGUAGE plpgsql
            AS $fn$
            DECLARE
                actor_col text := TG_ARGV[0];
            BEGIN
                -- Sanctioned mutation (memo D1): only the actor column changes,
                -- and its new value is NULL. NEW.<col> IS NULL alone would also
                -- admit reattribution to another user; the jsonb diff pins every
                -- other column to its OLD value.
                IF TG_OP = 'UPDATE'
                    AND (to_jsonb(NEW) ->> actor_col) IS NULL
                    AND (to_jsonb(NEW) - actor_col) = (to_jsonb(OLD) - actor_col)
                THEN
                    RETURN NEW;
                END IF;

                RAISE EXCEPTION '% is append-only: % rejected', TG_TABLE_NAME, TG_OP
                    USING ERRCODE = 'P0001';
            END;
            $fn$
            SQL);

        foreach (self::LEDGERS as $table => $config) {
            DB::statement("DROP TRIGGER IF EXISTS {$config['trigger']} ON {$table}");
            DB::statement("
                CREATE TRIGGER {$config['trigger']}
                BEFORE UPDATE OR DELETE ON {$table}
                FOR EACH ROW EXECUTE FUNCTION enforce_ledger_append_only('{$config['actor_col']}')
            ");
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        foreach (self::LEDGERS as $table => $config) {
            DB::statement("DROP TRIGGER IF EXISTS {$config['trigger']} ON {$table}");
        }

        DB::statement('DROP FUNCTION IF EXISTS enforce_ledger_append_only()');
    }
};
