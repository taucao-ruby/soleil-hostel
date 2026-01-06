<?php

namespace Tests\Unit\Repositories;

use App\Models\Booking;
use App\Repositories\EloquentBookingRepository;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Mockery;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for EloquentBookingRepository
 *
 * These tests verify the repository's data access layer in complete isolation.
 * 
 * TESTING STRATEGY:
 * - Instance method tests: Direct Mockery mocks (fast)
 * - Static method tests: @runInSeparateProcess with alias: mocks (isolated)
 *
 * IMPORTANT:
 * - No database connection required
 * - No Laravel TestCase (uses pure PHPUnit)
 * - All tests are deterministic and fast
 *
 * @covers \App\Repositories\EloquentBookingRepository
 */
class EloquentBookingRepositoryTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    // ========== INSTANCE METHOD TESTS (FAST) ==========

    /**
     * @test
     * @covers \App\Repositories\EloquentBookingRepository::update
     */
    public function update_returns_true_on_success(): void
    {
        $repository = new EloquentBookingRepository();
        $data = ['status' => 'confirmed'];
        
        $mockBooking = Mockery::mock(Booking::class);
        $mockBooking->shouldReceive('update')
            ->once()
            ->with($data)
            ->andReturn(true);

        $result = $repository->update($mockBooking, $data);

        $this->assertTrue($result);
    }

    /**
     * @test
     * @covers \App\Repositories\EloquentBookingRepository::update
     */
    public function update_returns_false_on_failure(): void
    {
        $repository = new EloquentBookingRepository();
        $data = ['status' => 'confirmed'];
        
        $mockBooking = Mockery::mock(Booking::class);
        $mockBooking->shouldReceive('update')
            ->once()
            ->with($data)
            ->andReturn(false);

        $result = $repository->update($mockBooking, $data);

        $this->assertFalse($result);
    }

    /**
     * @test
     * @covers \App\Repositories\EloquentBookingRepository::delete
     */
    public function delete_returns_true_on_success(): void
    {
        $repository = new EloquentBookingRepository();
        
        $mockBooking = Mockery::mock(Booking::class);
        $mockBooking->shouldReceive('delete')
            ->once()
            ->andReturn(true);

        $result = $repository->delete($mockBooking);

        $this->assertTrue($result);
    }

    /**
     * @test
     * @covers \App\Repositories\EloquentBookingRepository::delete
     */
    public function delete_returns_false_on_failure(): void
    {
        $repository = new EloquentBookingRepository();
        
        $mockBooking = Mockery::mock(Booking::class);
        $mockBooking->shouldReceive('delete')
            ->once()
            ->andReturn(false);

        $result = $repository->delete($mockBooking);

        $this->assertFalse($result);
    }

    /**
     * @test
     * @covers \App\Repositories\EloquentBookingRepository::restore
     */
    public function restore_returns_true_on_success(): void
    {
        $repository = new EloquentBookingRepository();
        
        $mockBooking = Mockery::mock(Booking::class);
        $mockBooking->shouldReceive('restore')
            ->once()
            ->andReturn(true);

        $result = $repository->restore($mockBooking);

        $this->assertTrue($result);
    }

    /**
     * @test
     * @covers \App\Repositories\EloquentBookingRepository::restore
     */
    public function restore_returns_false_on_failure(): void
    {
        $repository = new EloquentBookingRepository();
        
        $mockBooking = Mockery::mock(Booking::class);
        $mockBooking->shouldReceive('restore')
            ->once()
            ->andReturn(false);

        $result = $repository->restore($mockBooking);

        $this->assertFalse($result);
    }

    /**
     * @test
     * @covers \App\Repositories\EloquentBookingRepository::forceDelete
     */
    public function forceDelete_returns_true_on_success(): void
    {
        $repository = new EloquentBookingRepository();
        
        $mockBooking = Mockery::mock(Booking::class);
        $mockBooking->shouldReceive('forceDelete')
            ->once()
            ->andReturn(true);

        $result = $repository->forceDelete($mockBooking);

        $this->assertTrue($result);
    }

    /**
     * @test
     * @covers \App\Repositories\EloquentBookingRepository::forceDelete
     */
    public function forceDelete_returns_false_on_failure(): void
    {
        $repository = new EloquentBookingRepository();
        
        $mockBooking = Mockery::mock(Booking::class);
        $mockBooking->shouldReceive('forceDelete')
            ->once()
            ->andReturn(false);

        $result = $repository->forceDelete($mockBooking);

        $this->assertFalse($result);
    }

    // ========== STATIC METHOD TESTS (SEPARATE PROCESS) ==========

    /**
     * @test
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     * @covers \App\Repositories\EloquentBookingRepository::findById
     */
    public function findById_returns_booking_when_found(): void
    {
        $bookingId = 42;
        $mockBooking = Mockery::mock('alias:' . Booking::class);
        $mockBooking->shouldReceive('find')
            ->once()
            ->with($bookingId)
            ->andReturn($mockBooking);

        $repository = new EloquentBookingRepository();
        $result = $repository->findById($bookingId);

        $this->assertSame($mockBooking, $result);
    }

    /**
     * @test
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     * @covers \App\Repositories\EloquentBookingRepository::findById
     */
    public function findById_returns_null_when_not_found(): void
    {
        $bookingId = 999;
        $mockBooking = Mockery::mock('alias:' . Booking::class);
        $mockBooking->shouldReceive('find')
            ->once()
            ->with($bookingId)
            ->andReturn(null);

        $repository = new EloquentBookingRepository();
        $result = $repository->findById($bookingId);

        $this->assertNull($result);
    }

    /**
     * @test
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     * @covers \App\Repositories\EloquentBookingRepository::findByIdOrFail
     */
    public function findByIdOrFail_returns_booking_when_found(): void
    {
        $bookingId = 42;
        $mockBooking = Mockery::mock('alias:' . Booking::class);
        $mockBooking->shouldReceive('findOrFail')
            ->once()
            ->with($bookingId)
            ->andReturn($mockBooking);

        $repository = new EloquentBookingRepository();
        $result = $repository->findByIdOrFail($bookingId);

        $this->assertSame($mockBooking, $result);
    }

    /**
     * @test
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     * @covers \App\Repositories\EloquentBookingRepository::findByIdWithRelations
     */
    public function findByIdWithRelations_returns_booking_with_eager_loaded_relations(): void
    {
        $bookingId = 42;
        $relations = ['room', 'user'];
        
        // Create a partial alias mock that extends Booking
        $mockModel = Mockery::mock('alias:' . Booking::class)->makePartial();
        
        $mockBuilder = Mockery::mock(Builder::class);
        $mockBuilder->shouldReceive('find')
            ->once()
            ->with($bookingId)
            ->andReturn($mockModel);

        $mockModel->shouldReceive('with')
            ->once()
            ->with($relations)
            ->andReturn($mockBuilder);

        $repository = new EloquentBookingRepository();
        $result = $repository->findByIdWithRelations($bookingId, $relations);

        $this->assertSame($mockModel, $result);
    }

    /**
     * @test
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     * @covers \App\Repositories\EloquentBookingRepository::findByIdWithRelations
     */
    public function findByIdWithRelations_returns_null_when_not_found(): void
    {
        $bookingId = 999;
        $relations = ['room'];

        $mockBuilder = Mockery::mock(Builder::class);
        $mockBuilder->shouldReceive('find')
            ->once()
            ->with($bookingId)
            ->andReturn(null);

        $mockModel = Mockery::mock('alias:' . Booking::class);
        $mockModel->shouldReceive('with')
            ->once()
            ->with($relations)
            ->andReturn($mockBuilder);

        $repository = new EloquentBookingRepository();
        $result = $repository->findByIdWithRelations($bookingId, $relations);

        $this->assertNull($result);
    }

    /**
     * @test
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     * @covers \App\Repositories\EloquentBookingRepository::create
     */
    public function create_returns_new_booking_instance(): void
    {
        $data = [
            'room_id' => 1,
            'user_id' => 5,
            'check_in' => '2026-01-10',
            'check_out' => '2026-01-15',
            'guest_name' => 'John Doe',
            'guest_email' => 'john@example.com',
            'status' => 'pending',
        ];

        // Create a partial alias mock that extends Booking
        $mockModel = Mockery::mock('alias:' . Booking::class)->makePartial();
        
        $mockModel->shouldReceive('create')
            ->once()
            ->with($data)
            ->andReturn($mockModel);

        $repository = new EloquentBookingRepository();
        $result = $repository->create($data);

        $this->assertSame($mockModel, $result);
    }

    /**
     * @test
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     * @covers \App\Repositories\EloquentBookingRepository::getByUserId
     */
    public function getByUserId_returns_collection_with_default_columns(): void
    {
        $userId = 5;
        $expectedCollection = new Collection();

        $mockBuilder = Mockery::mock(Builder::class);
        $mockBuilder->shouldReceive('get')
            ->once()
            ->andReturn($expectedCollection);

        $mockModel = Mockery::mock('alias:' . Booking::class);
        $mockModel->shouldReceive('where')
            ->once()
            ->with('user_id', $userId)
            ->andReturn($mockBuilder);

        $repository = new EloquentBookingRepository();
        $result = $repository->getByUserId($userId);

        $this->assertSame($expectedCollection, $result);
    }

    /**
     * @test
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     * @covers \App\Repositories\EloquentBookingRepository::getByUserId
     */
    public function getByUserId_applies_custom_columns_and_relations(): void
    {
        $userId = 5;
        $columns = ['id', 'room_id', 'status'];
        $relations = ['room'];
        $expectedCollection = new Collection();

        $mockBuilder = Mockery::mock(Builder::class);
        $mockBuilder->shouldReceive('with')
            ->once()
            ->with($relations)
            ->andReturnSelf();
        $mockBuilder->shouldReceive('select')
            ->once()
            ->with($columns)
            ->andReturnSelf();
        $mockBuilder->shouldReceive('get')
            ->once()
            ->andReturn($expectedCollection);

        $mockModel = Mockery::mock('alias:' . Booking::class);
        $mockModel->shouldReceive('where')
            ->once()
            ->with('user_id', $userId)
            ->andReturn($mockBuilder);

        $repository = new EloquentBookingRepository();
        $result = $repository->getByUserId($userId, $columns, $relations);

        $this->assertSame($expectedCollection, $result);
    }

    /**
     * @test
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     * @covers \App\Repositories\EloquentBookingRepository::getByUserIdOrderedByCheckIn
     */
    public function getByUserIdOrderedByCheckIn_returns_collection_ordered_desc(): void
    {
        $userId = 5;
        $expectedCollection = new Collection();

        $mockBuilder = Mockery::mock(Builder::class);
        $mockBuilder->shouldReceive('orderBy')
            ->once()
            ->with('check_in', 'desc')
            ->andReturnSelf();
        $mockBuilder->shouldReceive('get')
            ->once()
            ->andReturn($expectedCollection);

        $mockModel = Mockery::mock('alias:' . Booking::class);
        $mockModel->shouldReceive('where')
            ->once()
            ->with('user_id', $userId)
            ->andReturn($mockBuilder);

        $repository = new EloquentBookingRepository();
        $result = $repository->getByUserIdOrderedByCheckIn($userId);

        $this->assertSame($expectedCollection, $result);
    }

    /**
     * @test
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     * @covers \App\Repositories\EloquentBookingRepository::getByUserIdOrderedByCheckIn
     */
    public function getByUserIdOrderedByCheckIn_applies_columns_and_relations(): void
    {
        $userId = 5;
        $columns = ['id', 'check_in', 'check_out'];
        $relations = ['room', 'user'];
        $expectedCollection = new Collection();

        $mockBuilder = Mockery::mock(Builder::class);
        $mockBuilder->shouldReceive('with')
            ->once()
            ->with($relations)
            ->andReturnSelf();
        $mockBuilder->shouldReceive('select')
            ->once()
            ->with($columns)
            ->andReturnSelf();
        $mockBuilder->shouldReceive('orderBy')
            ->once()
            ->with('check_in', 'desc')
            ->andReturnSelf();
        $mockBuilder->shouldReceive('get')
            ->once()
            ->andReturn($expectedCollection);

        $mockModel = Mockery::mock('alias:' . Booking::class);
        $mockModel->shouldReceive('where')
            ->once()
            ->with('user_id', $userId)
            ->andReturn($mockBuilder);

        $repository = new EloquentBookingRepository();
        $result = $repository->getByUserIdOrderedByCheckIn($userId, $columns, $relations);

        $this->assertSame($expectedCollection, $result);
    }

    // ========== OVERLAP/CONFLICT DETECTION QUERIES ==========

    /**
     * @test
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     * @covers \App\Repositories\EloquentBookingRepository::findOverlappingBookings
     */
    public function findOverlappingBookings_returns_collection_of_conflicts(): void
    {
        $roomId = 1;
        $checkIn = Carbon::parse('2026-01-10');
        $checkOut = Carbon::parse('2026-01-15');
        $expectedCollection = new Collection();

        $mockBuilder = Mockery::mock(Builder::class);
        $mockBuilder->shouldReceive('get')
            ->once()
            ->andReturn($expectedCollection);

        $mockModel = Mockery::mock('alias:' . Booking::class);
        $mockModel->shouldReceive('overlappingBookings')
            ->once()
            ->with($roomId, $checkIn, $checkOut, null)
            ->andReturn($mockBuilder);

        $repository = new EloquentBookingRepository();
        $result = $repository->findOverlappingBookings($roomId, $checkIn, $checkOut);

        $this->assertSame($expectedCollection, $result);
    }

    /**
     * @test
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     * @covers \App\Repositories\EloquentBookingRepository::findOverlappingBookings
     */
    public function findOverlappingBookings_excludes_specified_booking_id(): void
    {
        $roomId = 1;
        $checkIn = Carbon::parse('2026-01-10');
        $checkOut = Carbon::parse('2026-01-15');
        $excludeBookingId = 99;
        $expectedCollection = new Collection();

        $mockBuilder = Mockery::mock(Builder::class);
        $mockBuilder->shouldReceive('get')
            ->once()
            ->andReturn($expectedCollection);

        $mockModel = Mockery::mock('alias:' . Booking::class);
        $mockModel->shouldReceive('overlappingBookings')
            ->once()
            ->with($roomId, $checkIn, $checkOut, $excludeBookingId)
            ->andReturn($mockBuilder);

        $repository = new EloquentBookingRepository();
        $result = $repository->findOverlappingBookings($roomId, $checkIn, $checkOut, $excludeBookingId);

        $this->assertSame($expectedCollection, $result);
    }

    /**
     * @test
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     * @covers \App\Repositories\EloquentBookingRepository::hasOverlappingBookings
     */
    public function hasOverlappingBookings_returns_true_when_conflicts_exist(): void
    {
        $roomId = 1;
        $checkIn = Carbon::parse('2026-01-10');
        $checkOut = Carbon::parse('2026-01-15');

        $mockBuilder = Mockery::mock(Builder::class);
        $mockBuilder->shouldReceive('exists')
            ->once()
            ->andReturn(true);

        $mockModel = Mockery::mock('alias:' . Booking::class);
        $mockModel->shouldReceive('overlappingBookings')
            ->once()
            ->with($roomId, $checkIn, $checkOut, null)
            ->andReturn($mockBuilder);

        $repository = new EloquentBookingRepository();
        $result = $repository->hasOverlappingBookings($roomId, $checkIn, $checkOut);

        $this->assertTrue($result);
    }

    /**
     * @test
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     * @covers \App\Repositories\EloquentBookingRepository::hasOverlappingBookings
     */
    public function hasOverlappingBookings_returns_false_when_no_conflicts(): void
    {
        $roomId = 1;
        $checkIn = Carbon::parse('2026-01-10');
        $checkOut = Carbon::parse('2026-01-15');

        $mockBuilder = Mockery::mock(Builder::class);
        $mockBuilder->shouldReceive('exists')
            ->once()
            ->andReturn(false);

        $mockModel = Mockery::mock('alias:' . Booking::class);
        $mockModel->shouldReceive('overlappingBookings')
            ->once()
            ->with($roomId, $checkIn, $checkOut, null)
            ->andReturn($mockBuilder);

        $repository = new EloquentBookingRepository();
        $result = $repository->hasOverlappingBookings($roomId, $checkIn, $checkOut);

        $this->assertFalse($result);
    }

    /**
     * @test
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     * @covers \App\Repositories\EloquentBookingRepository::findOverlappingBookingsWithLock
     *
     * COMPLEX MOCK EXAMPLE: This tests the pessimistic locking chain:
     * Booking::query()->overlappingBookings(...)->withLock()->get()
     *
     * The withLock() scope internally calls lockForUpdate() for pessimistic locking.
     * This is critical for preventing race conditions in concurrent booking scenarios.
     */
    public function findOverlappingBookingsWithLock_applies_pessimistic_lock(): void
    {
        $roomId = 1;
        $checkIn = Carbon::parse('2026-01-10');
        $checkOut = Carbon::parse('2026-01-15');
        $expectedCollection = new Collection();

        // Build mock chain: query() -> overlappingBookings() -> withLock() -> get()
        $mockBuilder = Mockery::mock(Builder::class);
        $mockBuilder->shouldReceive('overlappingBookings')
            ->once()
            ->with($roomId, $checkIn, $checkOut, null)
            ->andReturnSelf();
        $mockBuilder->shouldReceive('withLock')
            ->once()
            ->andReturnSelf();
        $mockBuilder->shouldReceive('get')
            ->once()
            ->andReturn($expectedCollection);

        $mockModel = Mockery::mock('alias:' . Booking::class);
        $mockModel->shouldReceive('query')
            ->once()
            ->andReturn($mockBuilder);

        $repository = new EloquentBookingRepository();
        $result = $repository->findOverlappingBookingsWithLock($roomId, $checkIn, $checkOut);

        $this->assertSame($expectedCollection, $result);
    }

    /**
     * @test
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     * @covers \App\Repositories\EloquentBookingRepository::findOverlappingBookingsWithLock
     */
    public function findOverlappingBookingsWithLock_excludes_specified_booking_id(): void
    {
        $roomId = 1;
        $checkIn = Carbon::parse('2026-01-10');
        $checkOut = Carbon::parse('2026-01-15');
        $excludeBookingId = 50;
        $expectedCollection = new Collection();

        $mockBuilder = Mockery::mock(Builder::class);
        $mockBuilder->shouldReceive('overlappingBookings')
            ->once()
            ->with($roomId, $checkIn, $checkOut, $excludeBookingId)
            ->andReturnSelf();
        $mockBuilder->shouldReceive('withLock')
            ->once()
            ->andReturnSelf();
        $mockBuilder->shouldReceive('get')
            ->once()
            ->andReturn($expectedCollection);

        $mockModel = Mockery::mock('alias:' . Booking::class);
        $mockModel->shouldReceive('query')
            ->once()
            ->andReturn($mockBuilder);

        $repository = new EloquentBookingRepository();
        $result = $repository->findOverlappingBookingsWithLock($roomId, $checkIn, $checkOut, $excludeBookingId);

        $this->assertSame($expectedCollection, $result);
    }

    /**
     * @test
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     * @covers \App\Repositories\EloquentBookingRepository::hasOverlappingBookingsWithLock
     */
    public function hasOverlappingBookingsWithLock_returns_true_with_lock_applied(): void
    {
        $roomId = 1;
        $checkIn = Carbon::parse('2026-01-10');
        $checkOut = Carbon::parse('2026-01-15');

        $mockBuilder = Mockery::mock(Builder::class);
        $mockBuilder->shouldReceive('overlappingBookings')
            ->once()
            ->with($roomId, $checkIn, $checkOut, null)
            ->andReturnSelf();
        $mockBuilder->shouldReceive('withLock')
            ->once()
            ->andReturnSelf();
        $mockBuilder->shouldReceive('exists')
            ->once()
            ->andReturn(true);

        $mockModel = Mockery::mock('alias:' . Booking::class);
        $mockModel->shouldReceive('query')
            ->once()
            ->andReturn($mockBuilder);

        $repository = new EloquentBookingRepository();
        $result = $repository->hasOverlappingBookingsWithLock($roomId, $checkIn, $checkOut);

        $this->assertTrue($result);
    }

    /**
     * @test
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     * @covers \App\Repositories\EloquentBookingRepository::hasOverlappingBookingsWithLock
     */
    public function hasOverlappingBookingsWithLock_returns_false_when_no_conflicts(): void
    {
        $roomId = 1;
        $checkIn = Carbon::parse('2026-01-10');
        $checkOut = Carbon::parse('2026-01-15');

        $mockBuilder = Mockery::mock(Builder::class);
        $mockBuilder->shouldReceive('overlappingBookings')
            ->once()
            ->with($roomId, $checkIn, $checkOut, null)
            ->andReturnSelf();
        $mockBuilder->shouldReceive('withLock')
            ->once()
            ->andReturnSelf();
        $mockBuilder->shouldReceive('exists')
            ->once()
            ->andReturn(false);

        $mockModel = Mockery::mock('alias:' . Booking::class);
        $mockModel->shouldReceive('query')
            ->once()
            ->andReturn($mockBuilder);

        $repository = new EloquentBookingRepository();
        $result = $repository->hasOverlappingBookingsWithLock($roomId, $checkIn, $checkOut);

        $this->assertFalse($result);
    }

    // ========== SOFT DELETE QUERIES ==========

    /**
     * @test
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     * @covers \App\Repositories\EloquentBookingRepository::getTrashed
     */
    public function getTrashed_returns_only_soft_deleted_bookings(): void
    {
        $expectedCollection = new Collection();

        $mockBuilder = Mockery::mock(Builder::class);
        $mockBuilder->shouldReceive('get')
            ->once()
            ->andReturn($expectedCollection);

        $mockModel = Mockery::mock('alias:' . Booking::class);
        $mockModel->shouldReceive('onlyTrashed')
            ->once()
            ->andReturn($mockBuilder);

        $repository = new EloquentBookingRepository();
        $result = $repository->getTrashed();

        $this->assertSame($expectedCollection, $result);
    }

    /**
     * @test
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     * @covers \App\Repositories\EloquentBookingRepository::getTrashed
     */
    public function getTrashed_applies_relations_when_provided(): void
    {
        $relations = ['room', 'user'];
        $expectedCollection = new Collection();

        $mockBuilder = Mockery::mock(Builder::class);
        $mockBuilder->shouldReceive('with')
            ->once()
            ->with($relations)
            ->andReturnSelf();
        $mockBuilder->shouldReceive('get')
            ->once()
            ->andReturn($expectedCollection);

        $mockModel = Mockery::mock('alias:' . Booking::class);
        $mockModel->shouldReceive('onlyTrashed')
            ->once()
            ->andReturn($mockBuilder);

        $repository = new EloquentBookingRepository();
        $result = $repository->getTrashed($relations);

        $this->assertSame($expectedCollection, $result);
    }

    /**
     * @test
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     * @covers \App\Repositories\EloquentBookingRepository::findTrashedById
     */
    public function findTrashedById_returns_soft_deleted_booking(): void
    {
        $bookingId = 42;

        // Create a partial alias mock that extends Booking
        $mockModel = Mockery::mock('alias:' . Booking::class)->makePartial();
        
        $mockBuilder = Mockery::mock(Builder::class);
        $mockBuilder->shouldReceive('find')
            ->once()
            ->with($bookingId)
            ->andReturn($mockModel);

        $mockModel->shouldReceive('onlyTrashed')
            ->once()
            ->andReturn($mockBuilder);

        $repository = new EloquentBookingRepository();
        $result = $repository->findTrashedById($bookingId);

        $this->assertSame($mockModel, $result);
    }

    /**
     * @test
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     * @covers \App\Repositories\EloquentBookingRepository::findTrashedById
     */
    public function findTrashedById_returns_null_when_not_found(): void
    {
        $bookingId = 999;

        $mockBuilder = Mockery::mock(Builder::class);
        $mockBuilder->shouldReceive('find')
            ->once()
            ->with($bookingId)
            ->andReturn(null);

        $mockModel = Mockery::mock('alias:' . Booking::class);
        $mockModel->shouldReceive('onlyTrashed')
            ->once()
            ->andReturn($mockBuilder);

        $repository = new EloquentBookingRepository();
        $result = $repository->findTrashedById($bookingId);

        $this->assertNull($result);
    }

    /**
     * @test
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     * @covers \App\Repositories\EloquentBookingRepository::findTrashedById
     */
    public function findTrashedById_applies_relations_when_provided(): void
    {
        $bookingId = 42;
        $relations = ['room'];

        // Create a partial alias mock that extends Booking
        $mockModel = Mockery::mock('alias:' . Booking::class)->makePartial();
        
        $mockBuilder = Mockery::mock(Builder::class);
        $mockBuilder->shouldReceive('with')
            ->once()
            ->with($relations)
            ->andReturnSelf();
        $mockBuilder->shouldReceive('find')
            ->once()
            ->with($bookingId)
            ->andReturn($mockModel);

        $mockModel->shouldReceive('onlyTrashed')
            ->once()
            ->andReturn($mockBuilder);

        $repository = new EloquentBookingRepository();
        $result = $repository->findTrashedById($bookingId, $relations);

        $this->assertSame($mockModel, $result);
    }

    /**
     * @test
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     * @covers \App\Repositories\EloquentBookingRepository::getTrashedOlderThan
     */
    public function getTrashedOlderThan_filters_by_cutoff_date(): void
    {
        $cutoffDate = Carbon::parse('2025-12-01');
        $expectedCollection = new Collection();

        $mockBuilder = Mockery::mock(Builder::class);
        $mockBuilder->shouldReceive('where')
            ->once()
            ->with('deleted_at', '<', $cutoffDate)
            ->andReturnSelf();
        $mockBuilder->shouldReceive('get')
            ->once()
            ->andReturn($expectedCollection);

        $mockModel = Mockery::mock('alias:' . Booking::class);
        $mockModel->shouldReceive('onlyTrashed')
            ->once()
            ->andReturn($mockBuilder);

        $repository = new EloquentBookingRepository();
        $result = $repository->getTrashedOlderThan($cutoffDate);

        $this->assertSame($expectedCollection, $result);
    }

    // ========== ADMIN/LISTING QUERIES ==========

    /**
     * @test
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     * @covers \App\Repositories\EloquentBookingRepository::getAllWithTrashed
     */
    public function getAllWithTrashed_includes_soft_deleted_bookings(): void
    {
        $expectedCollection = new Collection();

        $mockBuilder = Mockery::mock(Builder::class);
        $mockBuilder->shouldReceive('get')
            ->once()
            ->andReturn($expectedCollection);

        $mockModel = Mockery::mock('alias:' . Booking::class);
        $mockModel->shouldReceive('withTrashed')
            ->once()
            ->andReturn($mockBuilder);

        $repository = new EloquentBookingRepository();
        $result = $repository->getAllWithTrashed();

        $this->assertSame($expectedCollection, $result);
    }

    /**
     * @test
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     * @covers \App\Repositories\EloquentBookingRepository::getAllWithTrashed
     */
    public function getAllWithTrashed_applies_relations_when_provided(): void
    {
        $relations = ['room', 'user', 'deletedBy'];
        $expectedCollection = new Collection();

        $mockBuilder = Mockery::mock(Builder::class);
        $mockBuilder->shouldReceive('with')
            ->once()
            ->with($relations)
            ->andReturnSelf();
        $mockBuilder->shouldReceive('get')
            ->once()
            ->andReturn($expectedCollection);

        $mockModel = Mockery::mock('alias:' . Booking::class);
        $mockModel->shouldReceive('withTrashed')
            ->once()
            ->andReturn($mockBuilder);

        $repository = new EloquentBookingRepository();
        $result = $repository->getAllWithTrashed($relations);

        $this->assertSame($expectedCollection, $result);
    }

    /**
     * @test
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     * @covers \App\Repositories\EloquentBookingRepository::getWithCommonRelations
     */
    public function getWithCommonRelations_applies_scope_and_returns_collection(): void
    {
        $expectedCollection = new Collection();

        $mockBuilder = Mockery::mock(Builder::class);
        $mockBuilder->shouldReceive('get')
            ->once()
            ->andReturn($expectedCollection);

        $mockModel = Mockery::mock('alias:' . Booking::class);
        $mockModel->shouldReceive('withCommonRelations')
            ->once()
            ->andReturn($mockBuilder);

        $repository = new EloquentBookingRepository();
        $result = $repository->getWithCommonRelations();

        $this->assertSame($expectedCollection, $result);
    }

    /**
     * @test
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     * @covers \App\Repositories\EloquentBookingRepository::query
     */
    public function query_returns_eloquent_builder_instance(): void
    {
        $mockBuilder = Mockery::mock(Builder::class);

        $mockModel = Mockery::mock('alias:' . Booking::class);
        $mockModel->shouldReceive('query')
            ->once()
            ->andReturn($mockBuilder);

        $repository = new EloquentBookingRepository();
        $result = $repository->query();

        $this->assertSame($mockBuilder, $result);
    }
}
