# Model Caching for Laravel

[![Laravel Package](https://img.shields.io/badge/Laravel-8.0--12.0-red.svg)](https://laravel.com)
[![PHP Version](https://img.shields.io/badge/PHP-8.0%2B-blue.svg)](https://php.net)
[![License](https://img.shields.io/badge/license-MIT-green.svg)](LICENSE)
[![Security](https://img.shields.io/badge/Security-Hardened-brightgreen.svg)](tests/Integration/Security)
[![Performance](https://img.shields.io/badge/Performance-Optimized-orange.svg)](tests/Integration/Performance)

**Enhanced Laravel Model Caching with Multi-Tenancy, Selective Invalidation, and Background Refresh Support**

This package provides automatic, intelligent caching for Eloquent models with advanced features for multi-tenant applications, selective cache invalidation, and background cache refresh capabilities.

## Features

- ✅ **Automatic, self-invalidating relationship caching** (eager-loading)
- ✅ **Automatic, self-invalidating model query caching**
- ✅ **Multi-tenancy support** - Separate cache per tenant
- ✅ **Selective cache invalidation** - Only invalidate related caches
- ✅ **Background cache refresh** - Keep cache fresh with queue jobs
- ✅ **Multiple database connections** - Separate cache per connection
- ✅ **Connection-specific cache stores** - Use different cache stores per database
- ✅ **Automatic use of cache tags** for cache providers that support them
- ✅ **Full-text search caching** - Search queries with cache support
- ✅ **Search index database** - MongoDB/PostgreSQL as search index (dev/test only)
- ✅ **Advanced query extensions** - Pagination, chunking, custom expiration
- ✅ **Bulk operations** - Bulk update/delete with cache invalidation
- ✅ **Transaction-aware caching** - Cache management in transactions
- ✅ **Optimistic locking** - Update conflict resolution
- ✅ **Laravel 8.0-12.0 support** - Backward compatible

## Requirements

- PHP 8.0+
- Laravel 8.0, 9.0, 10.0, 11.0, or 12.0

## Installation

```bash
composer require snowsoft/laravel-model-caching
```

The package will auto-register its service provider.

### Publish Configuration (Optional)

```bash
php artisan vendor:publish --provider="Snowsoft\LaravelModelCaching\Providers\Service" --tag="config"
```

## Quick Start

### Basic Usage

Add the `Cachable` trait to your models:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Snowsoft\LaravelModelCaching\Traits\Cachable;

class Product extends Model
{
    use Cachable;

    // That's it! All queries are now cached automatically
}
```

Or extend from `CachedModel`:

```php
<?php

namespace App\Models;

use Snowsoft\LaravelModelCaching\CachedModel;

class Product extends CachedModel
{
    // All queries are cached automatically
}
```

### Recommended: Base Model

For better maintainability, create a base model:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Snowsoft\LaravelModelCaching\Traits\Cachable;

abstract class BaseModel extends Model
{
    use Cachable;
}
```

Then extend your models:

```php
class Product extends BaseModel
{
    // ...
}
```

## Advanced Features

### Multi-Tenancy Support

Enable multi-tenancy in your `.env`:

```env
MODEL_CACHE_MULTI_TENANCY_ENABLED=true
MODEL_CACHE_TENANT_RESOLVER=fn() => auth()->user()->tenant_id
```

Or use tenant-specific cache stores:

```env
MODEL_CACHE_USE_TENANT_STORE=true
MODEL_CACHE_TENANT_STORE_PATTERN=tenant_{tenant_id}
```

See [MULTI_TENANCY_USAGE.md](MULTI_TENANCY_USAGE.md) for detailed documentation.

### Selective Cache Invalidation

By default, the package uses selective cache invalidation - only related caches are cleared when a model is updated. This is much more efficient than clearing all cache.

To disable (use legacy behavior):

```env
MODEL_CACHE_USE_SELECTIVE_INVALIDATION=false
```

### Background Cache Refresh

Enable background cache refresh to keep your cache fresh:

```env
MODEL_CACHE_BACKGROUND_REFRESH_ENABLED=true
MODEL_CACHE_REFRESH_QUEUE=default
MODEL_CACHE_REFRESH_DELAY=0
```

Use in your code:

```php
use Snowsoft\LaravelModelCaching\Services\CacheRefreshService;

$service = app(CacheRefreshService::class);

// Refresh cache for a model
$service->refreshModelCache($product);

// Queue a refresh job
$service->queueRefresh($product, [], 'default');
```

### Multiple Database Connections

Configure different cache stores per database connection:

```php
// config/laravel-model-caching.php
'connection-stores' => [
    'mysql' => 'redis',
    'pgsql' => 'memcached',
    'mongodb' => 'redis_mongodb',
    'tenant_db_1' => 'redis_tenant_1',
],
```

**Supported Databases:**
- ✅ MySQL/MariaDB
- ✅ PostgreSQL (fully tested)
- ✅ SQLite
- ✅ MongoDB (with jenssegers/mongodb or mongodb/laravel-mongodb)

See [DATABASE_SUPPORT.md](DATABASE_SUPPORT.md) for detailed PostgreSQL and MongoDB usage guide.

## Configuration

### Environment Variables

```env
# Enable/disable caching
MODEL_CACHE_ENABLED=true

# Use database keying (recommended for multi-tenant)
MODEL_CACHE_USE_DATABASE_KEYING=true

# Custom cache store
MODEL_CACHE_STORE=redis

# Multi-tenancy
MODEL_CACHE_MULTI_TENANCY_ENABLED=false
MODEL_CACHE_TENANT_RESOLVER=fn() => auth()->user()->tenant_id
MODEL_CACHE_USE_TENANT_STORE=false
MODEL_CACHE_TENANT_STORE_PATTERN=tenant_{tenant_id}

# Selective invalidation
MODEL_CACHE_USE_SELECTIVE_INVALIDATION=true

# Background refresh
MODEL_CACHE_BACKGROUND_REFRESH_ENABLED=false
MODEL_CACHE_REFRESH_QUEUE=default
MODEL_CACHE_REFRESH_DELAY=0

# Connection-specific stores (JSON format)
MODEL_CACHE_CONNECTION_STORES={"mysql":"redis","pgsql":"memcached"}

# Search index (development/test only)
MODEL_CACHE_SEARCH_INDEX_ENABLED=false
MODEL_CACHE_SEARCH_INDEX_DRIVER=null # 'mongodb' or 'pgsql'
MODEL_CACHE_SEARCH_INDEX_CONNECTION=null # Connection name
```
<｜tool▁calls▁begin｜><｜tool▁call▁begin｜>
read_file

## Cache Drivers

### Recommended (Taggable)
- ✅ Redis (highly recommended for production)
- ✅ Memcached
- ✅ APC/APCu
- ✅ Array (for testing only)

### Supported with Limitations
- ⚠️ MongoDB (requires `mongodb/laravel-mongodb` package)
  - Tag support may be limited
  - See [CACHE_DRIVERS.md](CACHE_DRIVERS.md) for setup
- ⚠️ PostgreSQL Database Cache (using Database driver)
  - No tag support
  - Full cache flush on updates
  - Lower performance than Redis/Memcached
  - See [CACHE_DRIVERS.md](CACHE_DRIVERS.md) for setup

### Not Recommended (Non-taggable)
- ❌ Database (MySQL/PostgreSQL/SQLite)
- ❌ File
- ❌ DynamoDB

**Note:** Non-taggable drivers will flush the entire cache on model updates instead of selective invalidation.

**For detailed cache driver documentation, see [CACHE_DRIVERS.md](CACHE_DRIVERS.md)**

## Usage Examples

### Disable Caching for Specific Query

```php
$products = Product::disableCache()->where('status', 'active')->get();
```

### Manual Cache Flush

```php
// Flush cache for a specific model
Product::flushCache();

// Flush all model cache
php artisan modelCache:clear

// Flush cache for specific model
php artisan modelCache:clear --model="App\Models\Product"
```

### Cache Statistics

View cache statistics and performance metrics:

```bash
# General statistics
php artisan modelCache:stats

# Model-specific statistics
php artisan modelCache:stats --model="App\Models\Product"

# Tenant-specific statistics
php artisan modelCache:stats --tenant=123

# Detailed statistics
php artisan modelCache:stats --detailed
```

### Cache Health Check

Check cache system health:

```bash
php artisan modelCache:health
```

This command checks:
- Cache store availability
- Tag support
- Configuration validity
- Multi-tenancy setup

### Cache Debugging

Debug cache keys and operations:

```bash
# Debug specific cache key
php artisan modelCache:debug --key="cache_key_here"

# Debug model cache
php artisan modelCache:debug --model="App\Models\Product"

# Trace cache invalidation
php artisan modelCache:debug --model="App\Models\Product" --trace
```

### Cache Warming

Pre-warm cache for better performance:

```bash
# Warm specific model
php artisan modelCache:warm --model="App\Models\Product"

# Warm all models
php artisan modelCache:warm --all

# Warm with strategy (popular, recent, all)
php artisan modelCache:warm --model="App\Models\Product" --strategy=popular

# Queue warming process
php artisan modelCache:warm --model="App\Models\Product" --queue
```

### Cache Benchmarking

Benchmark cache performance:

```bash
# Benchmark model
php artisan modelCache:benchmark --model="App\Models\Product"

# Custom iterations
php artisan modelCache:benchmark --model="App\Models\Product" --iterations=1000

# Compare with/without cache
php artisan modelCache:benchmark --model="App\Models\Product"
```

### Cache Cooldown

Prevent cache invalidation for a period:

```php
class Comment extends Model
{
    use Cachable;

    protected $cacheCooldownSeconds = 300; // 5 minutes
}

// Use in query
Comment::withCacheCooldownSeconds(30)->get();
```

### Selective Invalidation

The package automatically invalidates only related caches:

```php
// When a product is updated, only product-related caches are cleared
$product->update(['name' => 'New Name']);
// Related caches (categories, tags, etc.) are automatically invalidated
```

### Full-Text Search

Search queries with automatic caching:

```php
class Product extends CachedModel
{
    protected $searchable = ['name', 'description', 'sku'];
}

// Search with caching
$products = Product::search('laptop')->get();

// Search with filters
$products = Product::searchWithFilters('laptop', [
    'category_id' => 1,
    'status' => 'active'
])->get();
```

### Advanced Query Extensions

```php
// Cache-aware pagination
$products = Product::cachedPaginate(20);

// Custom cache expiration
$products = Product::cacheFor(3600)->where('status', 'active')->get();

// Custom cache tags
$products = Product::cacheTags(['featured'])->where('featured', true)->get();
```

### Bulk Operations

```php
// Bulk update with cache invalidation
$updated = Product::bulkUpdate(
    ['status' => 'active'],
    ['category_id' => 1]
);

// Transaction-aware bulk operations
use Snowsoft\LaravelModelCaching\Services\UpdateCacheService;

$service = app(UpdateCacheService::class);
$updated = $service->transaction(function () {
    return Product::bulkUpdate(['status' => 'active'], ['id' => [1, 2, 3]]);
});
```

### Optimistic Locking

```php
use Snowsoft\LaravelModelCaching\Services\UpdateCacheService;

$service = app(UpdateCacheService::class);

// Update with version control
$product = Product::find(1);
$service->updateWithLock($product, ['name' => 'Updated'], $product->version);
```

## Performance

In testing, this package has shown:
- **Up to 900% performance improvement** on complex pages
- **Average 100% performance increase** on typical pages
- **Reduced database queries** from 700+ to single-digit on complex forms

## Package Conflicts

The following packages may conflict:
- `grimzy/laravel-mysql-spatial`
- `fico7489/laravel-pivot`
- `chelout/laravel-relationship-events`
- `spatie/laravel-query-builder`
- `dwightwatson/rememberable`
- `kalnoy/nestedset`
- `laravel-adjacency-list`
- `archtechx/virtualcolumn`

## Known Limitations

- Lazy-loaded relationships (except belongs-to) are not cached
- Using `select()` clauses may require manual cache management
- Transactions may require manual cache flushing

## Testing

The package includes comprehensive security and performance tests:

### Security Tests
- **Cache Key Injection Tests**: Prevents SQL injection, XSS, and other injection attacks in cache keys
- **Tenant Isolation Tests**: Ensures multi-tenant cache isolation and prevents cross-tenant access
- **Cache Poisoning Tests**: Prevents cache poisoning attacks and ensures proper cache invalidation

### Performance Tests
- **Cache Hit/Miss Tests**: Measures cache hit rates and performance improvements
- **Memory Usage Tests**: Monitors memory consumption and prevents memory leaks
- **Concurrency Tests**: Tests concurrent access and race conditions
- **Query Performance Tests**: Measures query execution time improvements

Run all tests:
```bash
phpunit
```

Run specific test suites:
```bash
# Security tests
phpunit tests/Integration/Security/

# Performance tests
phpunit tests/Integration/Performance/
```

See [TESTING.md](TESTING.md) for detailed testing documentation.

## Troubleshooting

Having issues? Check our [Troubleshooting Guide](TROUBLESHOOTING.md) for common problems and solutions.

Common commands:
```bash
# Health check
php artisan modelCache:health

# View statistics
php artisan modelCache:stats

# Clear cache
php artisan modelCache:clear
```

## Cache Events

The package fires events for cache operations that you can listen to:

```php
use Snowsoft\LaravelModelCaching\Events\CacheHit;
use Snowsoft\LaravelModelCaching\Events\CacheMiss;
use Snowsoft\LaravelModelCaching\Events\CacheInvalidated;

Event::listen(CacheHit::class, function ($event) {
    Log::info('Cache hit', ['key' => $event->key]);
});

Event::listen(CacheMiss::class, function ($event) {
    Log::info('Cache miss', ['key' => $event->key]);
});

Event::listen(CacheInvalidated::class, function ($event) {
    Log::info('Cache invalidated', ['model' => get_class($event->model)]);
});
```

See [EVENTS.md](EVENTS.md) for detailed event documentation.

## Testing Helpers

Use cache assertions in your tests:

```php
use Snowsoft\LaravelModelCaching\Testing\CacheAssertions;

class ProductTest extends TestCase
{
    use CacheAssertions;

    public function test_cache_is_used()
    {
        $this->assertCacheHit(Product::class, function() {
            return Product::all();
        });
    }
}
```

## Examples

See [EXAMPLES.md](EXAMPLES.md) for comprehensive usage examples and real-world scenarios.

## Search Index Database (Development/Test Only)

For development and test environments, you can use MongoDB or PostgreSQL as an intermediate search index database to improve search performance and test full-text search features.

**Important:** This feature is **automatically disabled in production** environments.

### Quick Setup

#### MongoDB

```env
MODEL_CACHE_SEARCH_INDEX_ENABLED=true
MODEL_CACHE_SEARCH_INDEX_DRIVER=mongodb
MODEL_CACHE_SEARCH_INDEX_CONNECTION=mongodb
```

#### PostgreSQL

```env
MODEL_CACHE_SEARCH_INDEX_ENABLED=true
MODEL_CACHE_SEARCH_INDEX_DRIVER=pgsql
MODEL_CACHE_SEARCH_INDEX_CONNECTION=pgsql_search
```

### Usage

```php
class Product extends CachedModel
{
    protected $searchable = ['name', 'description', 'sku'];
}

// Index automatically created/updated on model changes
$product = Product::create(['name' => 'Laptop', 'description' => 'Gaming']);

// Search uses index if enabled, falls back to LIKE queries otherwise
$products = Product::search('laptop')->get();
```

### Features

- ✅ **MongoDB Text Index** - Full-text search with MongoDB text indexes
- ✅ **PostgreSQL TSVECTOR** - Full-text search with PostgreSQL tsvector/tsquery
- ✅ **Automatic Index Management** - Indexes updated automatically on model changes
- ✅ **Index Rebuild** - Rebuild indexes for all models
- ✅ **Fallback Support** - Falls back to LIKE queries if index service is disabled

See [SEARCH_INDEX.md](SEARCH_INDEX.md) for detailed documentation.

## Search, Query & Update Features

See [SEARCH_QUERY_UPDATE_FEATURES.md](SEARCH_QUERY_UPDATE_FEATURES.md) for detailed documentation on:
- Full-text search with caching
- Advanced query extensions
- Bulk operations
- Transaction-aware caching
- Optimistic locking

## Additional Features

See [EXTRA_FEATURES.md](EXTRA_FEATURES.md) for a list of additional features and improvements that can be added to the package.

## Contributing

Contributions are welcome! Please read our [Contributing Guide](CONTRIBUTING.md) and [Code of Conduct](CODE_OF_CONDUCT.md).

## License

This package is open-sourced software licensed under the [MIT license](LICENSE).

## Credits

Originally created by [Mike Bronner](https://github.com/mikebronner).
Enhanced and maintained by [Snowsoft](https://snowsoft.com).

## Support

For issues, questions, or contributions, please visit our [GitHub repository](https://github.com/snowsoft/laravel-model-caching).
