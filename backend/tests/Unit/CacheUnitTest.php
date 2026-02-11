<?php

namespace Tests\Unit;

use Illuminate\Cache\ArrayStore;
use Illuminate\Cache\Repository;
use Illuminate\Contracts\Cache\Store;
use PHPUnit\Framework\TestCase;

class CacheUnitTest extends TestCase
{
    private Repository $cache;

    protected function setUp(): void
    {
        parent::setUp();
        // Use array store for testing without Redis
        $this->cache = new Repository(new ArrayStore);
    }

    public function test_cache_remember_stores_value(): void
    {
        $key = 'test:cache:key';

        $result1 = $this->cache->remember($key, 60, fn () => 'cached-value');
        $this->assertEquals('cached-value', $result1);

        $this->assertTrue($this->cache->has($key));

        $result2 = $this->cache->get($key);
        $this->assertEquals('cached-value', $result2);
    }

    public function test_cache_forget_works(): void
    {
        $key = 'test:forget:key';

        $this->cache->put($key, 'value', 60);
        $this->assertTrue($this->cache->has($key));

        $this->cache->forget($key);
        $this->assertFalse($this->cache->has($key));
    }

    public function test_cache_put_get(): void
    {
        $key = 'test:put:key';
        $value = ['id' => 1, 'name' => 'Test Room'];

        $this->cache->put($key, $value, 300);
        $retrieved = $this->cache->get($key);

        $this->assertEquals($value, $retrieved);
    }

    public function test_cache_increment_decrement(): void
    {
        $key = 'test:counter';

        $this->cache->put($key, 0);
        $this->cache->increment($key);
        $this->assertEquals(1, $this->cache->get($key));

        $this->cache->decrement($key);
        $this->assertEquals(0, $this->cache->get($key));
    }

    public function test_cache_many_operations(): void
    {
        $data = [
            'room:1' => ['value' => 'Room A', 'minutes' => 60],
            'room:2' => ['value' => 'Room B', 'minutes' => 60],
            'room:3' => ['value' => 'Room C', 'minutes' => 60],
        ];

        // Use putMany instead for Laravel compatibility
        foreach ($data as $key => $item) {
            $this->cache->put($key, $item['value'], $item['minutes']);
        }

        $this->assertEquals('Room A', $this->cache->get('room:1'));
        $this->assertEquals('Room B', $this->cache->get('room:2'));
        $this->assertEquals('Room C', $this->cache->get('room:3'));
    }
}
