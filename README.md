# Model Caching for Laravel

[![Laravel Package](https://img.shields.io/badge/Laravel-8.0--12.0-red.svg)](https://laravel.com)
[![PHP Version](https://img.shields.io/badge/PHP-8.0%2B-blue.svg)](https://php.net)
[![License](https://img.shields.io/badge/license-MIT-green.svg)](LICENSE)

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
    'tenant_db_1' => 'redis_tenant_1',
],
```

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
```

## Cache Drivers

### Recommended (Taggable)
- ✅ Redis
- ✅ Memcached
- ✅ APC
- ✅ Array (for testing)

### Not Recommended (Non-taggable)
- ❌ Database
- ❌ File
- ❌ DynamoDB

**Note:** Non-taggable drivers will flush the entire cache on model updates instead of selective invalidation.

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

## Contributing

Contributions are welcome! Please read our [Contributing Guide](CONTRIBUTING.md) and [Code of Conduct](CODE_OF_CONDUCT.md).

## License

This package is open-sourced software licensed under the [MIT license](LICENSE).

## Credits

Originally created by [Mike Bronner](https://github.com/mikebronner).
Enhanced and maintained by [Snowsoft](https://snowsoft.com).

## Support

For issues, questions, or contributions, please visit our [GitHub repository](https://github.com/snowsoft/laravel-model-caching).
