<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Update chk_src_incident_type CHECK constraint to include
 * equivalent_swap and complimentary_upgrade incident types.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE service_recovery_cases DROP CONSTRAINT IF EXISTS chk_src_incident_type');
            DB::statement("
                ALTER TABLE service_recovery_cases ADD CONSTRAINT chk_src_incident_type
                CHECK (incident_type IN (
                    'late_checkout_blocking_arrival', 'room_unavailable_maintenance',
                    'overbooking_no_room', 'equivalent_swap', 'complimentary_upgrade',
                    'internal_relocation', 'external_relocation'
                ))
            ");
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE service_recovery_cases DROP CONSTRAINT IF EXISTS chk_src_incident_type');
            DB::statement("
                ALTER TABLE service_recovery_cases ADD CONSTRAINT chk_src_incident_type
                CHECK (incident_type IN (
                    'late_checkout_blocking_arrival', 'room_unavailable_maintenance',
                    'overbooking_no_room', 'internal_relocation', 'external_relocation'
                ))
            ");
        }
    }
};
