<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Money-column CHECK constraint for momo_payments (F-81 successor).
 *
 * The F-81 hardening pass (2026_06_11_000001) added `>= 0` guards to every money
 * column that existed at the time, but `momo_payments` was created 10 days later
 * (2026_06_21_000001) and re-opened the same class: `expected_amount` is declared
 * `unsignedBigInteger`, which Laravel's PostgreSQL grammar emits as a plain
 * BIGINT — so a negative pinned amount is insertable at the storage layer with
 * only app-level enforcement. This closes that gap to match the F-81 invariant.
 *
 * `expected_amount` is NOT NULL (no NULL tolerance needed, unlike the nullable
 * F-81 columns). PostgreSQL only — SQLite does not support ALTER TABLE ADD
 * CONSTRAINT. DROP-then-ADD keeps up() idempotent against partially-applied
 * state, matching the 2026_06_11_000001 idiom.
 */
return new class extends Migration
{
    private string $name = 'chk_momo_payments_expected_amount_nonneg';

    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement("ALTER TABLE momo_payments DROP CONSTRAINT IF EXISTS {$this->name}");
        DB::statement("ALTER TABLE momo_payments ADD CONSTRAINT {$this->name} CHECK (expected_amount >= 0)");
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement("ALTER TABLE momo_payments DROP CONSTRAINT IF EXISTS {$this->name}");
    }
};
