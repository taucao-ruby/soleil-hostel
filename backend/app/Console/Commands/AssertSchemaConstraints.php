<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Verifies pg_extension.extname = 'btree_gist'.
 * Verifies pg_constraint.conname = 'no_overlapping_bookings' on bookings.
 * Expects pg_constraint.contype = 'x', condeferrable = false, condeferred = false.
 * Expects pg_get_constraintdef() to use room_id WITH = and daterange(check_in, check_out, '[)') WITH &&.
 * Expects pg_get_constraintdef() WHERE status IN ('pending', 'confirmed') AND deleted_at IS NULL.
 */
final class AssertSchemaConstraints extends Command
{
    protected $signature = 'db:assert-schema-constraints';

    protected $description = 'Assert production-critical PostgreSQL constraints are present';

    public function handle(): int
    {
        if (DB::getDriverName() !== 'pgsql') {
            $this->error('Schema assertion failed: DB_CONNECTION must be pgsql.');

            return Command::FAILURE;
        }

        $overlapConstraint = DB::table('pg_constraint')
            ->selectRaw('conname, contype, condeferrable, condeferred, pg_get_constraintdef(oid) as constraint_definition')
            ->where('conname', 'no_overlapping_bookings')
            ->whereRaw("conrelid = to_regclass('bookings')")
            ->first();

        if ($overlapConstraint === null) {
            $this->error("Schema assertion failed: pg_constraint 'no_overlapping_bookings' is missing.");

            return Command::FAILURE;
        }

        if ($overlapConstraint->contype !== 'x') {
            $this->error("Schema assertion failed: pg_constraint 'no_overlapping_bookings' must have contype 'x'.");

            return Command::FAILURE;
        }

        if ((bool) $overlapConstraint->condeferrable || (bool) $overlapConstraint->condeferred) {
            $this->error("Schema assertion failed: pg_constraint 'no_overlapping_bookings' must be non-deferrable and initially immediate.");

            return Command::FAILURE;
        }

        $constraintDefinition = strtolower((string) $overlapConstraint->constraint_definition);
        $hasRoomEquality = str_contains($constraintDefinition, 'room_id with =');
        $hasHalfOpenDateRange = str_contains($constraintDefinition, 'daterange(check_in, check_out')
            && str_contains($constraintDefinition, "'[)'")
            && str_contains($constraintDefinition, 'with &&');
        $hasSoftDeleteFilter = str_contains($constraintDefinition, 'deleted_at is null');
        $hasPendingStatus = str_contains($constraintDefinition, 'pending');
        $hasConfirmedStatus = str_contains($constraintDefinition, 'confirmed');

        if (! $hasRoomEquality || ! $hasHalfOpenDateRange) {
            $this->error("Schema assertion failed: pg_constraint 'no_overlapping_bookings' must use the expected room/date exclusion expression.");

            return Command::FAILURE;
        }

        if (! $hasSoftDeleteFilter || ! $hasPendingStatus || ! $hasConfirmedStatus) {
            $this->error("Schema assertion failed: pg_constraint 'no_overlapping_bookings' must filter active, non-deleted bookings.");

            return Command::FAILURE;
        }

        $hasBtreeGist = DB::table('pg_extension')
            ->where('extname', 'btree_gist')
            ->exists();

        if (! $hasBtreeGist) {
            $this->error("Schema assertion failed: pg_extension 'btree_gist' is missing.");

            return Command::FAILURE;
        }

        $this->info("Schema assertion passed: 'no_overlapping_bookings' and 'btree_gist' match expected PostgreSQL invariants.");

        return Command::SUCCESS;
    }
}
