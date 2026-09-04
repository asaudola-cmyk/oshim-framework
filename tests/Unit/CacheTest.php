<?php
declare(strict_types=1);

namespace Tests\Unit;

use Oshim\Testing\TestCase;
use Oshim\Cache\Drivers\MemoryCacheDriver;
use Oshim\Cache\Drivers\FileCacheDriver;
use Oshim\Cache\CacheManager;
use Oshim\Cache\Cache;

final class CacheTest extends TestCase
{
    public function testMemoryCacheDriver(): void
    {
        $driver = new MemoryCacheDriver();
        $this->assertNull($driver->get('missing'));
        $this->assertSame('default', $driver->get('missing', 'default'));

        $driver->set('key1', 'value1');
        $this->assertTrue($driver->has('key1'));
        $this->assertSame('value1', $driver->get('key1'));

        $driver->delete('key1');
        $this->assertFalse($driver->has('key1'));

        $remembered = $driver->remember('compute_key', 60, fn() => 42 * 2);
        $this->assertSame(84, $remembered);
        $this->assertSame(84, $driver->get('compute_key'));
    }

    public function testFileCacheDriver(): void
    {
        $driver = new FileCacheDriver();
        $driver->set('file_test', ['status' => 'OK', 'timestamp' => 12345]);

        $this->assertTrue($driver->has('file_test'));
        $data = $driver->get('file_test');
        $this->assertSame('OK', $data['status']);

        $driver->delete('file_test');
        $this->assertFalse($driver->has('file_test'));
    }

    public function testCacheFacade(): void
    {
        Cache::set('facade_key', 'sovereign_cache');
        $this->assertSame('sovereign_cache', Cache::get('facade_key'));
        $this->assertTrue(Cache::has('facade_key'));
        Cache::delete('facade_key');
        $this->assertFalse(Cache::has('facade_key'));
    }
}
