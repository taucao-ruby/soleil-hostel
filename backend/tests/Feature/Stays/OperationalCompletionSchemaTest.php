<?php

namespace Tests\Feature\Stays;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class OperationalCompletionSchemaTest extends TestCase
{
    use RefreshDatabase;

    #[\PHPUnit\Framework\Attributes\Test]
    public function rooms_table_has_readiness_and_classification_columns(): void
    {
        $this->assertTrue(Schema::hasColumns('rooms', [
            'readiness_status',
            'readiness_updated_at',
            'readiness_updated_by',
            'room_type_code',
            'room_tier',
        ]));
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function bookings_table_has_deposit_lifecycle_columns(): void
    {
        $this->assertTrue(Schema::hasColumns('bookings', [
            'deposit_amount',
            'deposit_collected_at',
            'deposit_status',
        ]));
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function service_recovery_cases_table_has_settlement_lifecycle_columns(): void
    {
        $this->assertTrue(Schema::hasColumns('service_recovery_cases', [
            'settlement_status',
            'settled_amount',
            'settled_at',
            'settlement_notes',
        ]));
    }
}
