<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Add CHECK constraints for data integrity (PostgreSQL only).
     *
     * - chk_bookings_dates: check_out > check_in (half-open interval invariant)
     * - chk_reviews_rating: rating BETWEEN 1 AND 5
     * - chk_rooms_price: price >= 0
     *
     * SQLite (used in tests) does not support ALTER TABLE ADD CONSTRAINT.
     * These rules are also enforced at the application layer.
     */
    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            // CHECK constraints are PostgreSQL-only.
            // SQLite (used in tests) does not support ALTER TABLE ADD CONSTRAINT.
            // Integrity enforced at app layer for SQLite.
            return;
        }

        DB::statement('ALTER TABLE bookings ADD CONSTRAINT chk_bookings_dates CHECK (check_out > check_in)');

        DB::statement('ALTER TABLE reviews ADD CONSTRAINT chk_reviews_rating CHECK (rating BETWEEN 1 AND 5)');

        DB::statement('ALTER TABLE rooms ADD CONSTRAINT chk_rooms_price CHECK (price >= 0)');
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('ALTER TABLE bookings DROP CONSTRAINT IF EXISTS chk_bookings_dates');
        DB::statement('ALTER TABLE reviews DROP CONSTRAINT IF EXISTS chk_reviews_rating');
        DB::statement('ALTER TABLE rooms DROP CONSTRAINT IF EXISTS chk_rooms_price');
    }
};
