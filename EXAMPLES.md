# Usage Examples

Bu dokümantasyon, Laravel Model Caching paketinin kullanım örneklerini içerir.

## Basic Examples

### Simple Model Caching

```php
use App\Models\Product;
use Snowsoft\LaravelModelCaching\Traits\Cachable;

class Product extends Model
{
    use Cachable;
}

// Otomatik cache'lenir
$products = Product::where('status', 'active')->get();
```

### Cache with Relations

```php
// İlişkiler de cache'lenir
$products = Product::with('category', 'tags')->get();
```

### Disable Cache for Specific Query

```php
// Bu query cache'lenmez
$products = Product::disableCache()->where('status', 'active')->get();
```

## Advanced Examples

### Multi-Tenancy

```php
// .env
MODEL_CACHE_MULTI_TENANCY_ENABLED=true
MODEL_CACHE_TENANT_RESOLVER=fn() => auth()->user()->tenant_id

// Her tenant için ayrı cache
$products = Product::all(); // Tenant'a özel cache
```

### Selective Invalidation

```php
// Sadece ilgili cache'ler temizlenir
$product = Product::find(1);
$product->update(['name' => 'New Name']);
// Sadece Product cache'leri temizlenir, Category cache'leri kalır
```

### Cache Cooldown

```php
class Comment extends Model
{
    use Cachable;

    protected $cacheCooldownSeconds = 300; // 5 dakika
}

// Cooldown süresince cache temizlenmez
$comment->update(['content' => 'Updated']);
```

### Custom Cache Relations

```php
class Product extends Model
{
    use Cachable;

    // Sadece bu relation'ları invalidate et
    protected $cachedRelations = ['category', 'tags'];
}
```

## Event Examples

### Logging Cache Operations

```php
use Snowsoft\LaravelModelCaching\Events\CacheHit;
use Snowsoft\LaravelModelCaching\Events\CacheMiss;

Event::listen(CacheHit::class, function ($event) {
    Log::info('Cache hit', [
        'model' => get_class($event->model),
        'key' => $event->key,
    ]);
});

Event::listen(CacheMiss::class, function ($event) {
    Log::info('Cache miss', [
        'model' => get_class($event->model),
        'key' => $event->key,
    ]);
});
```

## Command Examples

### Cache Statistics

```bash
# Genel istatistikler
php artisan modelCache:stats

# Model-specific
php artisan modelCache:stats --model="App\Models\Product"

# Tenant-specific
php artisan modelCache:stats --tenant=123

# Detaylı
php artisan modelCache:stats --detailed
```

### Cache Debugging

```bash
# Cache key debug
php artisan modelCache:debug --key="cache_key_here"

# Model debug
php artisan modelCache:debug --model="App\Models\Product"

# Trace invalidation
php artisan modelCache:debug --model="App\Models\Product" --trace
```

### Cache Warming

```bash
# Warm specific model
php artisan modelCache:warm --model="App\Models\Product"

# Warm all models
php artisan modelCache:warm --all

# Warm with strategy
php artisan modelCache:warm --model="App\Models\Product" --strategy=popular

# Queue warming
php artisan modelCache:warm --model="App\Models\Product" --queue
```

### Cache Health Check

```bash
php artisan modelCache:health
```

### Cache Benchmarking

```bash
# Benchmark model
php artisan modelCache:benchmark --model="App\Models\Product"

# Custom iterations
php artisan modelCache:benchmark --model="App\Models\Product" --iterations=1000

# Without cache comparison
php artisan modelCache:benchmark --model="App\Models\Product" --no-cache
```

## Testing Examples

### Using Cache Assertions

```php
use Snowsoft\LaravelModelCaching\Testing\CacheAssertions;

class ProductTest extends TestCase
{
    use CacheAssertions;

    public function test_cache_is_used()
    {
        // İlk çağrı - cache miss
        $this->assertCacheMiss(Product::class, function() {
            return Product::all();
        });

        // İkinci çağrı - cache hit
        $this->assertCacheHit(Product::class, function() {
            return Product::all();
        });
    }

    public function test_cache_invalidation()
    {
        $product = Product::create(['name' => 'Test']);

        $this->assertCacheInvalidated(Product::class, function() use ($product) {
            $product->update(['name' => 'Updated']);
        });
    }

    public function test_cache_key_exists()
    {
        Product::all();

        $key = Product::newQuery()->makeCacheKey(['*']);
        $this->assertCacheKeyExists(sha1($key));
    }
}
```

## Real-World Scenarios

### E-Commerce Product Catalog

```php
class Product extends Model
{
    use Cachable;

    protected $cachedRelations = ['category', 'images', 'variants'];

    // Popular products cache
    public static function popular()
    {
        return static::where('views', '>', 1000)
            ->orderBy('views', 'desc')
            ->get();
    }
}

// Cache'lenir
$popularProducts = Product::popular();
```

### Blog Post with Comments

```php
class Post extends Model
{
    use Cachable;

    protected $cacheCooldownSeconds = 60; // 1 dakika

    protected $cachedRelations = ['author', 'category'];
}

class Comment extends Model
{
    use Cachable;

    protected $cacheCooldownSeconds = 300; // 5 dakika
}

// Post cache'lenir, comment'ler ayrı cache'lenir
$post = Post::with('comments')->find(1);
```

### Multi-Tenant SaaS Application

```php
// .env
MODEL_CACHE_MULTI_TENANCY_ENABLED=true
MODEL_CACHE_USE_TENANT_STORE=true

// Her tenant için ayrı cache store
class User extends Model
{
    use Cachable;
}

// Tenant 1
TenantResolver::setResolver(fn() => 'tenant_1');
$users1 = User::all(); // tenant_1 store'dan

// Tenant 2
TenantResolver::setResolver(fn() => 'tenant_2');
$users2 = User::all(); // tenant_2 store'dan
```

## Performance Optimization Examples

### Using cachedRelations

```php
class Product extends Model
{
    use Cachable;

    // Sadece bu relation'ları invalidate et
    // Reflection kullanımını atlar, %60-70 daha hızlı
    protected $cachedRelations = [
        'category',      // BelongsTo
        'tags',          // BelongsToMany
        'reviews',       // HasMany
    ];
}
```

### Background Cache Refresh

```php
use Snowsoft\LaravelModelCaching\Services\CacheRefreshService;

$service = app(CacheRefreshService::class);

// Refresh cache in background
$service->queueRefresh($product, [
    ['method' => 'all'],
    ['method' => 'where', 'column' => 'id', 'value' => $product->id],
]);
```

## Troubleshooting Examples

### Debug Cache Key

```php
$query = Product::where('status', 'active');
$key = $query->makeCacheKey(['*']);
dd($key);
```

### Check Cache Tags

```php
$product = new Product();
$tags = $product->makeCacheTags();
dd($tags);
```

### Monitor Cache Events

```php
Event::listen(\Snowsoft\LaravelModelCaching\Events\CacheHit::class, function ($event) {
    Log::info('Cache hit', [
        'key' => $event->key,
        'model' => get_class($event->model),
    ]);
});
```
