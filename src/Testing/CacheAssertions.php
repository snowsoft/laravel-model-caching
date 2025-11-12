<?php

namespace Snowsoft\LaravelModelCaching\Testing;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Snowsoft\LaravelModelCaching\Events\CacheHit;
use Snowsoft\LaravelModelCaching\Events\CacheMiss;
use Snowsoft\LaravelModelCaching\Events\CacheInvalidated;

/**
 * Cache Assertions Trait
 *
 * Testing helpers for cache operations
 */
trait CacheAssertions
{
    /**
     * Assert that cache was hit
     */
    protected function assertCacheHit(string $model, callable $callback)
    {
        Event::fake([CacheHit::class, CacheMiss::class]);

        $callback();

        Event::assertDispatched(CacheHit::class, function ($event) use ($model) {
            return get_class($event->model) === $model;
        });
    }

    /**
     * Assert that cache was missed
     */
    protected function assertCacheMiss(string $model, callable $callback)
    {
        Event::fake([CacheHit::class, CacheMiss::class]);

        $callback();

        Event::assertDispatched(CacheMiss::class, function ($event) use ($model) {
            return get_class($event->model) === $model;
        });
    }

    /**
     * Assert that cache was invalidated
     */
    protected function assertCacheInvalidated(string $model, callable $callback)
    {
        Event::fake([CacheInvalidated::class]);

        $callback();

        Event::assertDispatched(CacheInvalidated::class, function ($event) use ($model) {
            return get_class($event->model) === $model;
        });
    }

    /**
     * Assert that cache key exists
     */
    protected function assertCacheKeyExists(string $key)
    {
        $this->assertTrue(
            Cache::has($key),
            "Cache key '{$key}' does not exist."
        );
    }

    /**
     * Assert that cache key does not exist
     */
    protected function assertCacheKeyMissing(string $key)
    {
        $this->assertFalse(
            Cache::has($key),
            "Cache key '{$key}' exists but should not."
        );
    }

    /**
     * Assert that cache value equals expected value
     */
    protected function assertCacheValueEquals(string $key, $expected)
    {
        $actual = Cache::get($key);
        $this->assertEquals($expected, $actual, "Cache value for key '{$key}' does not match expected value.");
    }

    /**
     * Clear all cache
     */
    protected function clearCache()
    {
        Cache::flush();
    }

    /**
     * Disable cache for testing
     */
    protected function disableCache()
    {
        config(['laravel-model-caching.enabled' => false]);
    }

    /**
     * Enable cache for testing
     */
    protected function enableCache()
    {
        config(['laravel-model-caching.enabled' => true]);
    }
}
