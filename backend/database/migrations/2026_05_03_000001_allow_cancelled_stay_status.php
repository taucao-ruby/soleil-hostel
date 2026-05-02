<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('ALTER TABLE stays DROP CONSTRAINT IF EXISTS chk_stays_stay_status');
        DB::statement("
            ALTER TABLE stays ADD CONSTRAINT chk_stays_stay_status
            CHECK (stay_status IN (
                'expected', 'in_house', 'late_checkout', 'checked_out',
                'no_show', 'relocated_internal', 'relocated_external', 'cancelled'
            ))
        ");
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('ALTER TABLE stays DROP CONSTRAINT IF EXISTS chk_stays_stay_status');
        DB::statement("
            ALTER TABLE stays ADD CONSTRAINT chk_stays_stay_status
            CHECK (stay_status IN (
                'expected', 'in_house', 'late_checkout', 'checked_out',
                'no_show', 'relocated_internal', 'relocated_external'
            ))
        ");
    }
};
