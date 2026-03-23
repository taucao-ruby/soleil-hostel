<?php

namespace Tests\Unit\Models;

use App\Models\Location;
use App\Models\Room;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class RoomOperationalStateTest extends TestCase
{
    #[\PHPUnit\Framework\Attributes\Test]
    public function scope_ready_returns_only_physically_ready_rooms(): void
    {
        Room::factory()->ready()->create();
        Room::factory()->dirty()->create();
        Room::factory()->outOfService()->create();

        $readyRooms = Room::query()->ready()->get();

        $this->assertCount(1, $readyRooms);
        $this->assertEquals('ready', $readyRooms->first()->readiness_status->value);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function equivalence_and_upgrade_helpers_respect_type_tier_capacity_and_location(): void
    {
        $sourceLocation = Location::factory()->create();
        $otherLocation = Location::factory()->create();

        $source = Room::factory()
            ->forLocation($sourceLocation)
            ->classified('dorm_4bed', 1)
            ->ready()
            ->create([
                'max_guests' => 4,
                'status' => 'available',
            ]);

        $sameLocationEquivalent = Room::factory()
            ->forLocation($sourceLocation)
            ->classified('dorm_4bed', 1)
            ->ready()
            ->create([
                'max_guests' => 6,
                'status' => 'available',
            ]);

        $crossLocationEquivalent = Room::factory()
            ->forLocation($otherLocation)
            ->classified('dorm_4bed', 1)
            ->ready()
            ->create([
                'max_guests' => 4,
                'status' => 'available',
            ]);

        $crossLocationUpgrade = Room::factory()
            ->forLocation($otherLocation)
            ->classified('private_deluxe', 3)
            ->ready()
            ->create([
                'max_guests' => 4,
                'status' => 'available',
            ]);

        Room::factory()
            ->forLocation($otherLocation)
            ->classified('dorm_4bed', 1)
            ->dirty()
            ->create([
                'max_guests' => 4,
                'status' => 'available',
            ]);

        $this->assertTrue($sameLocationEquivalent->isEquivalentTo($source));
        $this->assertTrue($crossLocationUpgrade->isUpgradeOver($source));
        $this->assertFalse($source->isEquivalentTo($source));

        $equivalentIds = Room::query()->equivalentTo($source)->pluck('id')->all();
        $this->assertContains($sameLocationEquivalent->id, $equivalentIds);
        $this->assertContains($crossLocationEquivalent->id, $equivalentIds);

        $upgradeIds = Room::query()->upgradeOver($source)->pluck('id')->all();
        $this->assertContains($crossLocationUpgrade->id, $upgradeIds);

        $crossLocationEquivalentIds = $source->equivalentCandidatesAt($otherLocation)->pluck('id')->all();
        $crossLocationUpgradeIds = $source->upgradeCandidatesAt($otherLocation)->pluck('id')->all();

        $this->assertSame([$crossLocationEquivalent->id], $crossLocationEquivalentIds);
        $this->assertSame([$crossLocationUpgrade->id], $crossLocationUpgradeIds);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function invalid_readiness_status_is_rejected_by_postgresql_check_constraint(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            $this->markTestSkipped('CHECK constraints require PostgreSQL');
        }

        $location = Location::factory()->create();

        $this->expectException(QueryException::class);

        DB::table('rooms')->insert([
            'location_id' => $location->id,
            'name' => 'Broken Room',
            'room_number' => '999',
            'description' => 'Invalid readiness payload',
            'price' => 100.00,
            'max_guests' => 2,
            'room_type_code' => null,
            'room_tier' => 1,
            'status' => 'available',
            'readiness_status' => 'broken',
            'lock_version' => 1,
            'created_at' => now()->toDateTimeString(),
            'updated_at' => now()->toDateTimeString(),
        ]);
    }
}
