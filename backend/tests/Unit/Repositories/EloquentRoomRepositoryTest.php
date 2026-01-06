<?php

namespace Tests\Unit\Repositories;

use App\Models\Room;
use App\Repositories\EloquentRoomRepository;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Mockery;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for EloquentRoomRepository
 *
 * These tests verify the repository's data access layer in complete isolation.
 * 
 * TESTING STRATEGY:
 * - Instance method tests: Direct Mockery mocks (fast)
 * - Static method tests: @runInSeparateProcess with alias: mocks (isolated)
 * - DB facade tests: Using Mockery::mock() with shouldReceive
 *
 * IMPORTANT:
 * - No database connection required
 * - No Laravel TestCase (uses pure PHPUnit)
 * - All tests are deterministic and fast
 *
 * @covers \App\Repositories\EloquentRoomRepository
 */
class EloquentRoomRepositoryTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    // ========== INSTANCE METHOD TESTS (FAST) ==========

    /**
     * @test
     * @covers \App\Repositories\EloquentRoomRepository::refresh
     */
    public function refresh_calls_refresh_on_model_and_returns_it(): void
    {
        $repository = new EloquentRoomRepository();
        
        $mockRoom = Mockery::mock(Room::class);
        $mockRoom->shouldReceive('refresh')
            ->once()
            ->andReturnNull();

        $result = $repository->refresh($mockRoom);

        $this->assertSame($mockRoom, $result);
    }

    /**
     * @test
     * @covers \App\Repositories\EloquentRoomRepository::refresh
     */
    public function refresh_preserves_model_identity(): void
    {
        $repository = new EloquentRoomRepository();
        
        $mockRoom = Mockery::mock(Room::class);
        $mockRoom->shouldReceive('refresh')
            ->once()
            ->andReturnNull();

        $result = $repository->refresh($mockRoom);

        // Verify same object reference is returned
        $this->assertSame($mockRoom, $result);
    }

    // ========== STATIC METHOD TESTS (SEPARATE PROCESS) ==========

    /**
     * @test
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     * @covers \App\Repositories\EloquentRoomRepository::findByIdWithBookings
     */
    public function findByIdWithBookings_returns_room_with_eager_loaded_bookings(): void
    {
        $roomId = 1;
        $expectedColumns = ['id', 'name', 'description', 'price', 'max_guests', 'status', 'lock_version', 'created_at', 'updated_at'];

        // Create a partial alias mock that extends Room
        $mockModel = Mockery::mock('alias:' . Room::class)->makePartial();
        
        $mockBuilder = Mockery::mock(Builder::class);
        $mockBuilder->shouldReceive('select')
            ->once()
            ->with($expectedColumns)
            ->andReturnSelf();
        $mockBuilder->shouldReceive('find')
            ->once()
            ->with($roomId)
            ->andReturn($mockModel);

        $mockModel->shouldReceive('with')
            ->once()
            ->with('bookings')
            ->andReturn($mockBuilder);

        $repository = new EloquentRoomRepository();
        $result = $repository->findByIdWithBookings($roomId);

        $this->assertSame($mockModel, $result);
    }

    /**
     * @test
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     * @covers \App\Repositories\EloquentRoomRepository::findByIdWithBookings
     */
    public function findByIdWithBookings_returns_null_when_room_not_found(): void
    {
        $roomId = 999;
        $expectedColumns = ['id', 'name', 'description', 'price', 'max_guests', 'status', 'lock_version', 'created_at', 'updated_at'];

        $mockBuilder = Mockery::mock(Builder::class);
        $mockBuilder->shouldReceive('select')
            ->once()
            ->with($expectedColumns)
            ->andReturnSelf();
        $mockBuilder->shouldReceive('find')
            ->once()
            ->with($roomId)
            ->andReturn(null);

        $mockModel = Mockery::mock('alias:' . Room::class);
        $mockModel->shouldReceive('with')
            ->once()
            ->with('bookings')
            ->andReturn($mockBuilder);

        $repository = new EloquentRoomRepository();
        $result = $repository->findByIdWithBookings($roomId);

        $this->assertNull($result);
    }

    /**
     * @test
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     * @covers \App\Repositories\EloquentRoomRepository::findByIdWithConfirmedBookings
     */
    public function findByIdWithConfirmedBookings_returns_room_with_filtered_bookings(): void
    {
        $roomId = 1;

        // Create a partial alias mock that extends Room
        $mockModel = Mockery::mock('alias:' . Room::class)->makePartial();
        
        $mockBuilder = Mockery::mock(Builder::class);
        $mockBuilder->shouldReceive('find')
            ->once()
            ->with($roomId)
            ->andReturn($mockModel);

        $mockModel->shouldReceive('with')
            ->once()
            ->withArgs(function ($relations) {
                return is_array($relations) && isset($relations['bookings']) && is_callable($relations['bookings']);
            })
            ->andReturn($mockBuilder);

        $repository = new EloquentRoomRepository();
        $result = $repository->findByIdWithConfirmedBookings($roomId);

        $this->assertSame($mockModel, $result);
    }

    /**
     * @test
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     * @covers \App\Repositories\EloquentRoomRepository::findByIdWithConfirmedBookings
     */
    public function findByIdWithConfirmedBookings_returns_null_when_room_not_found(): void
    {
        $roomId = 999;

        $mockBuilder = Mockery::mock(Builder::class);
        $mockBuilder->shouldReceive('find')
            ->once()
            ->with($roomId)
            ->andReturn(null);

        $mockModel = Mockery::mock('alias:' . Room::class);
        $mockModel->shouldReceive('with')
            ->once()
            ->andReturn($mockBuilder);

        $repository = new EloquentRoomRepository();
        $result = $repository->findByIdWithConfirmedBookings($roomId);

        $this->assertNull($result);
    }

    /**
     * @test
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     * @covers \App\Repositories\EloquentRoomRepository::getAllOrderedByName
     */
    public function getAllOrderedByName_returns_collection_sorted_by_name(): void
    {
        $expectedCollection = new Collection();
        $expectedColumns = ['id', 'name', 'description', 'price', 'max_guests', 'status', 'lock_version', 'created_at', 'updated_at'];

        $mockBuilder = Mockery::mock(Builder::class);
        $mockBuilder->shouldReceive('orderBy')
            ->once()
            ->with('name')
            ->andReturnSelf();
        $mockBuilder->shouldReceive('get')
            ->once()
            ->andReturn($expectedCollection);

        $mockModel = Mockery::mock('alias:' . Room::class);
        $mockModel->shouldReceive('select')
            ->once()
            ->with($expectedColumns)
            ->andReturn($mockBuilder);

        $repository = new EloquentRoomRepository();
        $result = $repository->getAllOrderedByName();

        $this->assertSame($expectedCollection, $result);
    }

    /**
     * @test
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     * @covers \App\Repositories\EloquentRoomRepository::getAllOrderedByName
     */
    public function getAllOrderedByName_returns_empty_collection_when_no_rooms(): void
    {
        $expectedCollection = new Collection();
        $expectedColumns = ['id', 'name', 'description', 'price', 'max_guests', 'status', 'lock_version', 'created_at', 'updated_at'];

        $mockBuilder = Mockery::mock(Builder::class);
        $mockBuilder->shouldReceive('orderBy')
            ->once()
            ->with('name')
            ->andReturnSelf();
        $mockBuilder->shouldReceive('get')
            ->once()
            ->andReturn($expectedCollection);

        $mockModel = Mockery::mock('alias:' . Room::class);
        $mockModel->shouldReceive('select')
            ->once()
            ->with($expectedColumns)
            ->andReturn($mockBuilder);

        $repository = new EloquentRoomRepository();
        $result = $repository->getAllOrderedByName();

        $this->assertCount(0, $result);
    }

    /**
     * @test
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     * @covers \App\Repositories\EloquentRoomRepository::hasOverlappingConfirmedBookings
     *
     * COMPLEX MOCK EXAMPLE: This tests the overlap detection chain:
     * Room::find($roomId)->bookings()->where(...)->where(...)->where(...)->exists()
     *
     * This is a critical revenue protection query that checks for booking conflicts.
     * The half-open interval logic [check_in, check_out) is implemented as:
     * - check_in < $checkOut (existing booking starts before new checkout)
     * - check_out > $checkIn (existing booking ends after new checkin)
     */
    public function hasOverlappingConfirmedBookings_returns_true_when_conflicts_exist(): void
    {
        $roomId = 1;
        $checkIn = '2026-01-10';
        $checkOut = '2026-01-15';

        // Build mock chain: find() -> bookings() -> where() -> where() -> where() -> exists()
        $mockRelationBuilder = Mockery::mock(HasMany::class);
        $mockRelationBuilder->shouldReceive('where')
            ->once()
            ->with('status', 'confirmed')
            ->andReturnSelf();
        $mockRelationBuilder->shouldReceive('where')
            ->once()
            ->with('check_in', '<', $checkOut)
            ->andReturnSelf();
        $mockRelationBuilder->shouldReceive('where')
            ->once()
            ->with('check_out', '>', $checkIn)
            ->andReturnSelf();
        $mockRelationBuilder->shouldReceive('exists')
            ->once()
            ->andReturn(true);

        // Use stdClass with __call to avoid alias conflict
        $mockRoom = new class($mockRelationBuilder) {
            private $relationBuilder;
            public function __construct($relationBuilder) {
                $this->relationBuilder = $relationBuilder;
            }
            public function bookings() {
                return $this->relationBuilder;
            }
        };

        $mockModel = Mockery::mock('alias:' . Room::class);
        $mockModel->shouldReceive('find')
            ->once()
            ->with($roomId)
            ->andReturn($mockRoom);

        $repository = new EloquentRoomRepository();
        $result = $repository->hasOverlappingConfirmedBookings($roomId, $checkIn, $checkOut);

        $this->assertTrue($result);
    }

    /**
     * @test
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     * @covers \App\Repositories\EloquentRoomRepository::hasOverlappingConfirmedBookings
     */
    public function hasOverlappingConfirmedBookings_returns_false_when_no_conflicts(): void
    {
        $roomId = 1;
        $checkIn = '2026-01-10';
        $checkOut = '2026-01-15';

        $mockRelationBuilder = Mockery::mock(HasMany::class);
        $mockRelationBuilder->shouldReceive('where')
            ->once()
            ->with('status', 'confirmed')
            ->andReturnSelf();
        $mockRelationBuilder->shouldReceive('where')
            ->once()
            ->with('check_in', '<', $checkOut)
            ->andReturnSelf();
        $mockRelationBuilder->shouldReceive('where')
            ->once()
            ->with('check_out', '>', $checkIn)
            ->andReturnSelf();
        $mockRelationBuilder->shouldReceive('exists')
            ->once()
            ->andReturn(false);

        // Use anonymous class to avoid alias conflict
        $mockRoom = new class($mockRelationBuilder) {
            private $relationBuilder;
            public function __construct($relationBuilder) {
                $this->relationBuilder = $relationBuilder;
            }
            public function bookings() {
                return $this->relationBuilder;
            }
        };

        $mockModel = Mockery::mock('alias:' . Room::class);
        $mockModel->shouldReceive('find')
            ->once()
            ->with($roomId)
            ->andReturn($mockRoom);

        $repository = new EloquentRoomRepository();
        $result = $repository->hasOverlappingConfirmedBookings($roomId, $checkIn, $checkOut);

        $this->assertFalse($result);
    }

    /**
     * @test
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     * @covers \App\Repositories\EloquentRoomRepository::create
     */
    public function create_returns_new_room_instance(): void
    {
        $data = [
            'name' => 'Deluxe Suite',
            'description' => 'A spacious room with ocean view',
            'price' => '150.00',
            'max_guests' => 4,
            'status' => 'available',
        ];

        // Create a partial alias mock that extends Room
        $mockModel = Mockery::mock('alias:' . Room::class)->makePartial();
        
        $mockModel->shouldReceive('create')
            ->once()
            ->with($data)
            ->andReturn($mockModel);

        $repository = new EloquentRoomRepository();
        $result = $repository->create($data);

        $this->assertSame($mockModel, $result);
    }

    /**
     * @test
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     * @covers \App\Repositories\EloquentRoomRepository::create
     */
    public function create_with_minimal_data(): void
    {
        $data = [
            'name' => 'Standard Room',
            'price' => '80.00',
            'max_guests' => 2,
            'status' => 'available',
        ];

        // Create a partial alias mock that extends Room
        $mockModel = Mockery::mock('alias:' . Room::class)->makePartial();
        
        $mockModel->shouldReceive('create')
            ->once()
            ->with($data)
            ->andReturn($mockModel);

        $repository = new EloquentRoomRepository();
        $result = $repository->create($data);

        $this->assertSame($mockModel, $result);
    }

    // ========== DB FACADE TESTS ==========

    /**
     * @test
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     * @covers \App\Repositories\EloquentRoomRepository::updateWithVersionCheck
     *
     * Tests the optimistic locking update pattern:
     * DB::table('rooms')->where('id', ...)->where('lock_version', ...)->update(...)
     *
     * This is critical for preventing race conditions during concurrent updates.
     * Returns 1 on success (version matched), 0 on failure (version mismatch).
     */
    public function updateWithVersionCheck_returns_one_on_successful_update(): void
    {
        $roomId = 1;
        $expectedVersion = 5;
        $data = ['name' => 'Updated Room Name', 'price' => '200.00'];

        $mockQueryBuilder = Mockery::mock(QueryBuilder::class);
        $mockQueryBuilder->shouldReceive('where')
            ->once()
            ->with('id', $roomId)
            ->andReturnSelf();
        $mockQueryBuilder->shouldReceive('where')
            ->once()
            ->with('lock_version', $expectedVersion)
            ->andReturnSelf();
        $mockQueryBuilder->shouldReceive('update')
            ->once()
            ->withArgs(function ($updateData) use ($data) {
                return isset($updateData['name']) &&
                       $updateData['name'] === $data['name'] &&
                       isset($updateData['price']) &&
                       $updateData['price'] === $data['price'] &&
                       isset($updateData['lock_version']) &&
                       isset($updateData['updated_at']);
            })
            ->andReturn(1);

        $mockDb = Mockery::mock('alias:' . DB::class);
        $mockDb->shouldReceive('table')
            ->once()
            ->with('rooms')
            ->andReturn($mockQueryBuilder);
        $mockDb->shouldReceive('raw')
            ->once()
            ->with('lock_version + 1')
            ->andReturn('lock_version + 1');

        $repository = new EloquentRoomRepository();
        $result = $repository->updateWithVersionCheck($roomId, $expectedVersion, $data);

        $this->assertEquals(1, $result);
    }

    /**
     * @test
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     * @covers \App\Repositories\EloquentRoomRepository::updateWithVersionCheck
     */
    public function updateWithVersionCheck_returns_zero_on_version_mismatch(): void
    {
        $roomId = 1;
        $staleVersion = 3;
        $data = ['name' => 'Updated Room Name'];

        $mockQueryBuilder = Mockery::mock(QueryBuilder::class);
        $mockQueryBuilder->shouldReceive('where')
            ->once()
            ->with('id', $roomId)
            ->andReturnSelf();
        $mockQueryBuilder->shouldReceive('where')
            ->once()
            ->with('lock_version', $staleVersion)
            ->andReturnSelf();
        $mockQueryBuilder->shouldReceive('update')
            ->once()
            ->andReturn(0);

        $mockDb = Mockery::mock('alias:' . DB::class);
        $mockDb->shouldReceive('table')
            ->once()
            ->with('rooms')
            ->andReturn($mockQueryBuilder);
        $mockDb->shouldReceive('raw')
            ->once()
            ->andReturn('lock_version + 1');

        $repository = new EloquentRoomRepository();
        $result = $repository->updateWithVersionCheck($roomId, $staleVersion, $data);

        $this->assertEquals(0, $result);
    }

    /**
     * @test
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     * @covers \App\Repositories\EloquentRoomRepository::deleteWithVersionCheck
     */
    public function deleteWithVersionCheck_returns_one_on_successful_delete(): void
    {
        $roomId = 1;
        $expectedVersion = 5;

        $mockQueryBuilder = Mockery::mock(QueryBuilder::class);
        $mockQueryBuilder->shouldReceive('where')
            ->once()
            ->with('id', $roomId)
            ->andReturnSelf();
        $mockQueryBuilder->shouldReceive('where')
            ->once()
            ->with('lock_version', $expectedVersion)
            ->andReturnSelf();
        $mockQueryBuilder->shouldReceive('delete')
            ->once()
            ->andReturn(1);

        $mockDb = Mockery::mock('alias:' . DB::class);
        $mockDb->shouldReceive('table')
            ->once()
            ->with('rooms')
            ->andReturn($mockQueryBuilder);

        $repository = new EloquentRoomRepository();
        $result = $repository->deleteWithVersionCheck($roomId, $expectedVersion);

        $this->assertEquals(1, $result);
    }

    /**
     * @test
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     * @covers \App\Repositories\EloquentRoomRepository::deleteWithVersionCheck
     */
    public function deleteWithVersionCheck_returns_zero_on_version_mismatch(): void
    {
        $roomId = 1;
        $staleVersion = 3;

        $mockQueryBuilder = Mockery::mock(QueryBuilder::class);
        $mockQueryBuilder->shouldReceive('where')
            ->once()
            ->with('id', $roomId)
            ->andReturnSelf();
        $mockQueryBuilder->shouldReceive('where')
            ->once()
            ->with('lock_version', $staleVersion)
            ->andReturnSelf();
        $mockQueryBuilder->shouldReceive('delete')
            ->once()
            ->andReturn(0);

        $mockDb = Mockery::mock('alias:' . DB::class);
        $mockDb->shouldReceive('table')
            ->once()
            ->with('rooms')
            ->andReturn($mockQueryBuilder);

        $repository = new EloquentRoomRepository();
        $result = $repository->deleteWithVersionCheck($roomId, $staleVersion);

        $this->assertEquals(0, $result);
    }

    /**
     * @test
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     * @covers \App\Repositories\EloquentRoomRepository::deleteWithVersionCheck
     */
    public function deleteWithVersionCheck_returns_zero_when_room_not_found(): void
    {
        $nonExistentRoomId = 999;
        $anyVersion = 1;

        $mockQueryBuilder = Mockery::mock(QueryBuilder::class);
        $mockQueryBuilder->shouldReceive('where')
            ->once()
            ->with('id', $nonExistentRoomId)
            ->andReturnSelf();
        $mockQueryBuilder->shouldReceive('where')
            ->once()
            ->with('lock_version', $anyVersion)
            ->andReturnSelf();
        $mockQueryBuilder->shouldReceive('delete')
            ->once()
            ->andReturn(0);

        $mockDb = Mockery::mock('alias:' . DB::class);
        $mockDb->shouldReceive('table')
            ->once()
            ->with('rooms')
            ->andReturn($mockQueryBuilder);

        $repository = new EloquentRoomRepository();
        $result = $repository->deleteWithVersionCheck($nonExistentRoomId, $anyVersion);

        $this->assertEquals(0, $result);
    }
}
