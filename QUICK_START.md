# Hızlı Başlangıç Kılavuzu

Bu kılavuz, Laravel Model Caching paketini hızlıca kullanmaya başlamanız için temel örnekleri içerir.

## Kurulum

```bash
composer require snowsoft/laravel-model-caching
```

## Temel Kullanım

### 1. Model'e Trait Ekleme

```php
use Snowsoft\LaravelModelCaching\Traits\Cachable;

class Product extends Model
{
    use Cachable;
}
```

### 2. İlk Sorgu

```php
// Otomatik cache'lenir
$products = Product::where('status', 'active')->get();
```

## Arama Özellikleri

### Basit Arama

```php
class Product extends CachedModel
{
    protected $searchable = ['name', 'description'];
}

// Arama
$products = Product::search('laptop')->get();
```

### Filtrelerle Arama

```php
$products = Product::searchWithFilters('laptop', [
    'category_id' => 1,
    'status' => 'active'
])->get();
```

## Sorgu Özellikleri

### Pagination

```php
// Cache-aware pagination
$products = Product::cachedPaginate(20);
```

### Custom Expiration

```php
// 1 saat cache'le
$products = Product::cacheFor(3600)->where('status', 'active')->get();
```

### Custom Tags

```php
// Özel tag'lerle
$products = Product::cacheTags(['featured'])->where('featured', true)->get();
```

## Veri Güncelleme

### Bulk Update

```php
// Toplu güncelleme
$updated = Product::bulkUpdate(
    ['status' => 'active'],
    ['category_id' => 1]
);
```

### Transaction-Aware

```php
use Snowsoft\LaravelModelCaching\Services\UpdateCacheService;

$service = app(UpdateCacheService::class);
$updated = $service->transaction(function () {
    return Product::bulkUpdate(['status' => 'active'], ['id' => [1, 2, 3]]);
});
```

## Artisan Commands

```bash
# Cache istatistikleri
php artisan modelCache:stats

# Sağlık kontrolü
php artisan modelCache:health

# Debug
php artisan modelCache:debug --model="App\Models\Product"

# Cache warming
php artisan modelCache:warm --model="App\Models\Product"

# Benchmark
php artisan modelCache:benchmark --model="App\Models\Product"
```

## Event'ler

```php
use Snowsoft\LaravelModelCaching\Events\CacheHit;

Event::listen(CacheHit::class, function ($event) {
    Log::info('Cache hit', ['key' => $event->key]);
});
```

## Daha Fazla Bilgi

- [README.md](README.md) - Tam dokümantasyon
- [EXAMPLES.md](EXAMPLES.md) - Detaylı örnekler
- [SEARCH_QUERY_UPDATE_FEATURES.md](SEARCH_QUERY_UPDATE_FEATURES.md) - Arama, sorgu ve güncelleme özellikleri
