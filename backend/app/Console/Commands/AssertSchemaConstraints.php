<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

final class AssertSchemaConstraints extends Command
{
    protected $signature = 'app:assert-schema-constraints';

    protected $description = 'Assert production-critical PostgreSQL constraints are present';

    public function handle(): int
    {
        if (DB::getDriverName() !== 'pgsql') {
            $this->error('Schema assertion failed: DB_CONNECTION must be pgsql.');

            return Command::FAILURE;
        }

        $hasOverlapConstraint = DB::table('pg_constraint')
            ->where('conname', 'no_overlapping_bookings')
            ->exists();

        if (! $hasOverlapConstraint) {
            $this->error("Schema assertion failed: pg_constraint 'no_overlapping_bookings' is missing.");

            return Command::FAILURE;
        }

        $hasBtreeGist = DB::table('pg_extension')
            ->where('extname', 'btree_gist')
            ->exists();

        if (! $hasBtreeGist) {
            $this->error("Schema assertion failed: pg_extension 'btree_gist' is missing.");

            return Command::FAILURE;
        }

        $this->info("Schema assertion passed: 'no_overlapping_bookings' and 'btree_gist' are present.");

        return Command::SUCCESS;
    }
}
