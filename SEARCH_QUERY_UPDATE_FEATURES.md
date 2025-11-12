# Arama, Sorgu ve Veri Güncelleme Özellikleri

Bu dokümantasyon, arama, sorgu ve veri güncelleme işlemleri için eklenen özellikleri açıklar.

## 🔍 Arama (Search) Özellikleri

### 1. Full-Text Search

**Özellik:** Full-text search sorguları için cache desteği.

**Kullanım:**
```php
class Product extends CachedModel
{
    // Arama yapılacak kolonları tanımla
    protected $searchable = ['name', 'description', 'sku'];
}

// Basit arama
$products = Product::search('laptop')->get();

// Çoklu terim arama
$products = Product::searchMultiple(['laptop', 'gaming'])->get();

// Filtrelerle arama
$products = Product::searchWithFilters('laptop', [
    'category_id' => 1,
    'status' => 'active'
])->get();

// Relevance scoring ile arama
$products = Product::searchRelevant('laptop')->get();
```

**Özellikler:**
- ✅ Otomatik cache'leme
- ✅ Searchable columns tanımlama
- ✅ Çoklu terim desteği
- ✅ Filtreleme desteği
- ✅ Relevance scoring

### 2. Search Cache Service

**Özellik:** Arama sonuçları için özel cache yönetimi.

**Kullanım:**
```php
use Snowsoft\LaravelModelCaching\Services\SearchCacheService;

$service = app(SearchCacheService::class);

// Search result'ı cache'le
$service->cacheSearchResult($product, 'laptop', $results, ['name', 'description']);

// Cache'den al
$cached = $service->getCachedSearchResult($product, 'laptop', ['name', 'description']);

// Search cache'i temizle
$service->clearSearchCache($product, 'laptop'); // Belirli term
$service->clearSearchCache($product); // Tüm search cache'leri
```

## 📊 Sorgu (Query) Özellikleri

### 1. Cache-Aware Pagination

**Özellik:** Pagination için özel cache desteği.

**Kullanım:**
```php
// Cache-aware pagination
$products = Product::cachedPaginate(20);

// Cache-aware simple pagination
$products = Product::cachedSimplePaginate(20);

// Cache-aware cursor pagination
$products = Product::cachedCursorPaginate(20);
```

### 2. Cache-Aware Chunking

**Özellik:** Büyük dataset'ler için chunk işlemleri.

**Kullanım:**
```php
// Cache-aware chunk
Product::cachedChunk(100, function ($products) {
    foreach ($products as $product) {
        // İşlemler
    }
});
```

### 3. Custom Cache Expiration

**Özellik:** Belirli sorgular için cache expiration.

**Kullanım:**
```php
// 1 saat cache'le
$products = Product::cacheFor(3600)->where('status', 'active')->get();

// 30 dakika cache'le
$products = Product::cacheFor(1800)->popular()->get();
```

### 4. Custom Cache Tags

**Özellik:** Belirli sorgular için özel cache tag'leri.

**Kullanım:**
```php
// Özel tag'lerle cache'le
$products = Product::cacheTags(['featured', 'homepage'])
    ->where('featured', true)
    ->get();

// Tag'lere göre temizle
Cache::tags(['featured'])->flush();
```

### 5. Advanced Query Methods

**Kullanım:**
```php
// Filtrelerle count
$count = Product::cachedCountWithFilters([
    'category_id' => 1,
    'status' => 'active'
]);

// Custom ordering ile cache
$products = Product::cachedOrderBy('created_at', 'desc');

// Distinct değerler
$categories = Product::cachedDistinct('category_id');
```

## 🔄 Veri Güncelleme (Update) Özellikleri

### 1. Bulk Update

**Özellik:** Toplu güncelleme işlemleri için cache desteği.

**Kullanım:**
```php
// Bulk update
$updated = Product::bulkUpdate(
    ['status' => 'inactive'],
    ['category_id' => 1]
);

// Bulk delete
$deleted = Product::bulkDelete([
    'status' => 'archived',
    'created_at' => '<', now()->subYear()
]);

// Bulk insert
Product::bulkInsert([
    ['name' => 'Product 1', 'price' => 100],
    ['name' => 'Product 2', 'price' => 200],
]);
```

### 2. Transaction-Aware Operations

**Özellik:** Transaction içinde cache yönetimi.

**Kullanım:**
```php
use Snowsoft\LaravelModelCaching\Services\UpdateCacheService;

$service = app(UpdateCacheService::class);

// Transaction-aware bulk update
$updated = $service->transaction(function () {
    return Product::bulkUpdate(['status' => 'active'], ['id' => [1, 2, 3]]);
});

// Transaction-aware bulk delete
$deleted = Product::transactionBulkDelete(['status' => 'archived']);
```

### 3. Optimistic Locking

**Özellik:** Update conflict'lerini önlemek için.

**Kullanım:**
```php
use Snowsoft\LaravelModelCaching\Services\UpdateCacheService;

$service = app(UpdateCacheService::class);

// Version kontrolü ile update
$product = Product::find(1);
$version = $product->version;

try {
    $service->updateWithLock($product, [
        'name' => 'Updated Name'
    ], $version);
} catch (\Exception $e) {
    // Conflict - başka bir process değiştirmiş
    // Fresh model al ve tekrar dene
    $product = $product->fresh();
}
```

### 4. Conflict Resolution

**Özellik:** Update conflict'lerini otomatik çözme.

**Kullanım:**
```php
use Snowsoft\LaravelModelCaching\Services\UpdateCacheService;

$service = app(UpdateCacheService::class);

// Conflict resolver ile update
$product = Product::find(1);

$updated = $service->updateWithConflictResolution($product, [
    'name' => 'New Name'
], function ($freshModel, $values) {
    // Conflict durumunda ne yapılacak
    // Örnek: Fresh model'in değerlerini kullan
    return array_merge($freshModel->toArray(), $values);
});
```

### 5. Update with Cache Refresh

**Özellik:** Update sonrası cache'i otomatik yenileme.

**Kullanım:**
```php
use Snowsoft\LaravelModelCaching\Services\UpdateCacheService;

$service = app(UpdateCacheService::class);

// Update ve cache refresh
$service->updateWithRefresh($product, [
    'name' => 'Updated Name'
], true); // true = cache'i yenile
```

### 6. Update or Insert

**Özellik:** Update veya insert işlemleri.

**Kullanım:**
```php
// Update or insert
$product = Product::updateOrInsert(
    ['sku' => 'PROD-001'],
    ['name' => 'Product Name', 'price' => 100]
);

// Upsert (multiple)
Product::upsert(
    [
        ['sku' => 'PROD-001', 'name' => 'Product 1'],
        ['sku' => 'PROD-002', 'name' => 'Product 2'],
    ],
    'sku', // Unique column
    ['name'] // Update columns
);
```

## 📝 Model Konfigürasyonu

### Searchable Columns

```php
class Product extends CachedModel
{
    // Arama yapılacak kolonlar
    protected $searchable = ['name', 'description', 'sku', 'tags'];
}
```

### Cache Relations

```php
class Product extends CachedModel
{
    // Cache'lenecek relation'lar
    protected $cachedRelations = ['category', 'tags', 'reviews'];
}
```

## 🎯 Kullanım Senaryoları

### Senaryo 1: E-Commerce Product Search

```php
class Product extends CachedModel
{
    protected $searchable = ['name', 'description', 'sku'];
    protected $cachedRelations = ['category', 'images'];
}

// Arama
$products = Product::searchWithFilters('laptop', [
    'category_id' => 1,
    'status' => 'active',
    'price' => ['>', 1000]
])
->cachedPaginate(20)
->get();
```

### Senaryo 2: Bulk Status Update

```php
// Toplu status güncelleme
$updated = Product::bulkUpdate(
    ['status' => 'active'],
    ['category_id' => 1]
);

// Transaction içinde
$service = app(UpdateCacheService::class);
$updated = $service->transaction(function () {
    return Product::bulkUpdate(['status' => 'active'], ['id' => [1, 2, 3]]);
});
```

### Senaryo 3: Search with Pagination

```php
// Arama sonuçları pagination ile
$products = Product::search('laptop')
    ->where('status', 'active')
    ->cachedPaginate(20);

// Her sayfa ayrı cache'lenir
```

### Senaryo 4: Optimistic Locking

```php
// Concurrent update'lerde conflict önleme
$product = Product::find(1);
$version = $product->version;

$service = app(UpdateCacheService::class);

try {
    $service->updateWithLock($product, [
        'stock' => $product->stock - 1
    ], $version);
} catch (\Exception $e) {
    // Conflict - fresh model al
    $product = $product->fresh();
    // Tekrar dene
}
```

## ⚡ Performans İpuçları

### 1. Search Cache Optimization

```php
// Searchable columns'ı sınırla
protected $searchable = ['name', 'description']; // Sadece gerekli kolonlar

// Search cache TTL kullan
$service = app(SearchCacheService::class);
$service->cacheSearchResult($model, $term, $results, [], [], 3600); // 1 saat
```

### 2. Bulk Operations

```php
// Büyük bulk operation'lar için transaction kullan
$service = app(UpdateCacheService::class);
$service->transaction(function () {
    Product::bulkUpdate(['status' => 'active'], ['category_id' => 1]);
});
```

### 3. Custom Cache Expiration

```php
// Sık değişen veriler için kısa TTL
$products = Product::cacheFor(300)->where('status', 'active')->get(); // 5 dakika

// Nadiren değişen veriler için uzun TTL
$categories = Category::cacheFor(86400)->all(); // 24 saat
```

## 🔒 Güvenlik Notları

### 1. Search Input Sanitization

```php
// Search term'i sanitize et
$term = htmlspecialchars($term, ENT_QUOTES, 'UTF-8');
$products = Product::search($term)->get();
```

### 2. Bulk Update Validation

```php
// Bulk update öncesi validate et
$values = validator($values, [
    'status' => 'required|in:active,inactive',
])->validate();

Product::bulkUpdate($values, $conditions);
```

## 📊 Monitoring

### Search Performance

```php
// Search cache hit/miss tracking
Event::listen(\Snowsoft\LaravelModelCaching\Events\CacheHit::class, function ($event) {
    if (str_contains($event->key, 'search:')) {
        Log::info('Search cache hit', ['key' => $event->key]);
    }
});
```

### Update Performance

```php
// Bulk update tracking
$start = microtime(true);
$updated = Product::bulkUpdate($values, $conditions);
$time = microtime(true) - $start;

Log::info('Bulk update completed', [
    'updated' => $updated,
    'time' => $time,
]);
```

## 🧪 Testing

### Search Testing

```php
use Snowsoft\LaravelModelCaching\Testing\CacheAssertions;

class ProductSearchTest extends TestCase
{
    use CacheAssertions;

    public function test_search_is_cached()
    {
        $this->assertCacheMiss(Product::class, function() {
            return Product::search('laptop')->get();
        });

        $this->assertCacheHit(Product::class, function() {
            return Product::search('laptop')->get();
        });
    }
}
```

### Bulk Update Testing

```php
public function test_bulk_update_invalidates_cache()
{
    Product::create(['name' => 'Test']);

    $this->assertCacheInvalidated(Product::class, function() {
        Product::bulkUpdate(['status' => 'active'], ['name' => 'Test']);
    });
}
```
