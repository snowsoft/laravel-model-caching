# Cache Events

Laravel Model Caching paketi, cache işlemlerini izlemek için event'ler sağlar.

## Available Events

### CacheHit
Cache hit olduğunda tetiklenir.

```php
use Snowsoft\LaravelModelCaching\Events\CacheHit;

Event::listen(CacheHit::class, function ($event) {
    Log::info('Cache hit', [
        'key' => $event->key,
        'model' => get_class($event->model),
        'tags' => $event->tags,
    ]);
});
```

### CacheMiss
Cache miss olduğunda tetiklenir.

```php
use Snowsoft\LaravelModelCaching\Events\CacheMiss;

Event::listen(CacheMiss::class, function ($event) {
    Log::info('Cache miss', [
        'key' => $event->key,
        'model' => get_class($event->model),
        'tags' => $event->tags,
    ]);
});
```

### CacheInvalidated
Cache invalidate edildiğinde tetiklenir.

```php
use Snowsoft\LaravelModelCaching\Events\CacheInvalidated;

Event::listen(CacheInvalidated::class, function ($event) {
    Log::info('Cache invalidated', [
        'model' => get_class($event->model),
        'tags' => $event->tags,
        'reason' => $event->reason,
    ]);
});
```

## Event Listener Examples

### Logging Cache Operations

```php
// app/Providers/EventServiceProvider.php
use Snowsoft\LaravelModelCaching\Events\CacheHit;
use Snowsoft\LaravelModelCaching\Events\CacheMiss;
use Snowsoft\LaravelModelCaching\Events\CacheInvalidated;

protected $listen = [
    CacheHit::class => [
        LogCacheHit::class,
    ],
    CacheMiss::class => [
        LogCacheMiss::class,
    ],
    CacheInvalidated::class => [
        LogCacheInvalidated::class,
    ],
];
```

### Analytics Tracking

```php
Event::listen(CacheHit::class, function ($event) {
    // Track cache hit rate
    Analytics::track('cache.hit', [
        'model' => get_class($event->model),
    ]);
});

Event::listen(CacheMiss::class, function ($event) {
    // Track cache miss
    Analytics::track('cache.miss', [
        'model' => get_class($event->model),
    ]);
});
```

### Performance Monitoring

```php
Event::listen(CacheHit::class, function ($event) {
    // Record cache hit performance
    PerformanceMonitor::record('cache.hit', [
        'key' => $event->key,
        'model' => get_class($event->model),
    ]);
});
```

### Cache Warming

```php
Event::listen(CacheMiss::class, function ($event) {
    // Warm related caches on miss
    if (shouldWarmCache($event->model)) {
        CacheWarmingService::warm($event->model);
    }
});
```

## Event Properties

### CacheHit / CacheMiss
- `$key` - Cache key
- `$model` - Model instance
- `$tags` - Cache tags array

### CacheInvalidated
- `$model` - Model instance
- `$tags` - Cache tags array
- `$reason` - Invalidation reason (e.g., 'model_updated', 'model_deleted')

## Usage in Tests

```php
use Snowsoft\LaravelModelCaching\Testing\CacheAssertions;
use Snowsoft\LaravelModelCaching\Events\CacheHit;

class ProductTest extends TestCase
{
    use CacheAssertions;

    public function test_cache_hit()
    {
        Event::fake([CacheHit::class]);

        Product::all();

        Event::assertDispatched(CacheHit::class);
    }
}
```
