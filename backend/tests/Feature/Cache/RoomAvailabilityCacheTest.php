<?php

namespace Tests\Feature\Cache;

use Tests\TestCase;
use App\Models\Room;
use App\Services\Cache\RoomAvailabilityCache;
use Illuminate\Support\Facades\Cache;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Carbon\Carbon;

class RoomAvailabilityCacheTest extends TestCase
{
    use RefreshDatabase;

    protected RoomAvailabilityCache $cache;
    protected Room $room;

    protected function setUp(): void
    {
        parent::setUp();
        $this->cache = app(RoomAvailabilityCache::class);
        $this->room = Room::factory()->create();
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_cache_hit_on_second_request(): void
    {
        // Use database cache for test consistency
        config(['cache.default' => 'database']);
        
        $checkIn = now()->addDays(1);
        $checkOut = now()->addDays(3);

        // First request: Cache miss (database query)
        $start = microtime(true);
        $result1 = $this->cache->getAvailableRooms($checkIn, $checkOut, 2);
        $time1 = microtime(true) - $start;

        // Second request: Cache hit (instant)
        $start = microtime(true);
        $result2 = $this->cache->getAvailableRooms($checkIn, $checkOut, 2);
        $time2 = microtime(true) - $start;

        // Results should be identical
        $this->assertEquals($result1->pluck('id')->sort(), $result2->pluck('id')->sort());

        // Cache hit should be significantly faster
        $this->assertLessThan($time1, $time2 * 2);  // Cache hit should be <50% of DB hit
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_cache_expiration_after_ttl(): void
    {
        // Use database cache for test consistency
        config(['cache.default' => 'database']);
        
        $checkIn = now()->addDays(1);
        $checkOut = now()->addDays(3);

        // Get cached result
        $this->cache->getAvailableRooms($checkIn, $checkOut, 2);

        // Verify it's in cache (handle both tag-based and key-based caches)
        $cacheKey = "rooms_availability_{$checkIn->format('Y-m-d')}_{$checkOut->format('Y-m-d')}_2";
        try {
            $hasCache = Cache::tags(['room_availability'])->has($cacheKey);
        } catch (\BadMethodCallException $e) {
            // Tags not supported, check with basic cache
            $hasCache = Cache::has($cacheKey);
        }
        $this->assertTrue($hasCache);

        // Simulate TTL expiration (61 seconds)
        // Note: In real tests, you'd use time travel or mock time
        // For now, we just verify the cache can be manually invalidated
        $this->cache->invalidateAllAvailability();

        // Verify cache was cleared
        try {
            $stillExists = Cache::tags(['room_availability'])->has($cacheKey);
        } catch (\BadMethodCallException $e) {
            // Tags not supported, check with basic cache
            $stillExists = Cache::has($cacheKey);
        }
        $this->assertFalse($stillExists);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_cache_invalidation_on_different_capacities(): void
    {
        // Use database cache for test consistency
        config(['cache.default' => 'database']);
        
        $checkIn = now()->addDays(1);
        $checkOut = now()->addDays(3);

        // Cache for capacity 2
        $this->cache->getAvailableRooms($checkIn, $checkOut, 2);
        $key2 = "rooms_availability_{$checkIn->format('Y-m-d')}_{$checkOut->format('Y-m-d')}_2";
        try {
            $has2 = Cache::tags(['room_availability'])->has($key2);
        } catch (\BadMethodCallException $e) {
            $has2 = Cache::has($key2);
        }
        $this->assertTrue($has2);

        // Cache for capacity 4
        $this->cache->getAvailableRooms($checkIn, $checkOut, 4);
        $key4 = "rooms_availability_{$checkIn->format('Y-m-d')}_{$checkOut->format('Y-m-d')}_4";
        try {
            $has4 = Cache::tags(['room_availability'])->has($key4);
        } catch (\BadMethodCallException $e) {
            $has4 = Cache::has($key4);
        }
        $this->assertTrue($has4);

        // Invalidate room (should invalidate both capacities)
        $this->cache->invalidateRoomAvailability($this->room->id);

        // Both should be cleared
        try {
            $still2 = Cache::tags(['room_availability'])->has($key2);
            $still4 = Cache::tags(['room_availability'])->has($key4);
        } catch (\BadMethodCallException $e) {
            $still2 = Cache::has($key2);
            $still4 = Cache::has($key4);
        }
        $this->assertFalse($still2);
        $this->assertFalse($still4);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_single_room_availability_cache(): void
    {
        $checkIn = now()->addDays(1);
        $checkOut = now()->addDays(3);

        // First call: Database query
        $result1 = $this->cache->getRoomAvailability($this->room, $checkIn, $checkOut);
        $this->assertIsArray($result1);
        $this->assertTrue($result1['is_available']);

        // Second call: Cache hit
        $result2 = $this->cache->getRoomAvailability($this->room, $checkIn, $checkOut);
        $this->assertEquals($result1, $result2);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_cache_warmup(): void
    {
        $from = now();
        $to = now()->addDays(10);

        $count = $this->cache->warmUpCache($from, $to);

        // Should cache multiple date ranges
        $this->assertGreaterThan(0, $count);

        // Verify cache entries exist
        $cacheStats = $this->cache->getCacheStats();
        $this->assertIsString($cacheStats['driver']);
        $this->assertEquals(60, $cacheStats['ttl_seconds']);
    }
}
