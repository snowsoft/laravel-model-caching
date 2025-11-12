# Arama ve Indexleme için Ara Veritabanı Desteği

Bu dokümantasyon, development/test ortamlarında arama ve indexleme için MongoDB veya PostgreSQL'in ara veritabanı olarak kullanılmasını açıklar.

## Genel Bakış

Production dışında (development/test), arama performansını artırmak ve full-text search özelliklerini test etmek için MongoDB veya PostgreSQL'i ara bir index veritabanı olarak kullanabilirsiniz.

**Önemli:** Bu özellik **sadece production dışında** çalışır. Production ortamında otomatik olarak devre dışıdır.

## Özellikler

- ✅ **MongoDB Text Index** - Full-text search için MongoDB text index desteği
- ✅ **PostgreSQL TSVECTOR** - PostgreSQL full-text search (tsvector/tsquery) desteği
- ✅ **Otomatik Index Güncelleme** - Model oluşturma/güncelleme/silme işlemlerinde otomatik index yönetimi
- ✅ **Index Rebuild** - Tüm model'ler için index'i yeniden oluşturma
- ✅ **Fallback Desteği** - Index servisi aktif değilse normal LIKE sorgularına geri döner

## Kurulum

### 1. MongoDB için

#### Paket Kurulumu

```bash
composer require mongodb/laravel-mongodb
```

#### Veritabanı Yapılandırması

```php
// config/database.php
'connections' => [
    'mongodb' => [
        'driver' => 'mongodb',
        'host' => env('MONGODB_HOST', 'localhost'),
        'port' => env('MONGODB_PORT', 27017),
        'database' => env('MONGODB_DATABASE', 'laravel'),
        'username' => env('MONGODB_USERNAME', ''),
        'password' => env('MONGODB_PASSWORD', ''),
        'options' => [
            'database' => env('MONGODB_AUTHENTICATION_DATABASE', 'admin'),
        ],
    ],
],
```

#### Environment Variables

```env
MODEL_CACHE_SEARCH_INDEX_ENABLED=true
MODEL_CACHE_SEARCH_INDEX_DRIVER=mongodb
MODEL_CACHE_SEARCH_INDEX_CONNECTION=mongodb
```

### 2. PostgreSQL için

#### Veritabanı Yapılandırması

```php
// config/database.php
'connections' => [
    'pgsql_search' => [
        'driver' => 'pgsql',
        'host' => env('PGSQL_SEARCH_HOST', '127.0.0.1'),
        'port' => env('PGSQL_SEARCH_PORT', '5432'),
        'database' => env('PGSQL_SEARCH_DATABASE', 'search_index'),
        'username' => env('PGSQL_SEARCH_USERNAME', 'postgres'),
        'password' => env('PGSQL_SEARCH_PASSWORD', ''),
        'charset' => 'utf8',
        'prefix' => '',
        'schema' => 'public',
    ],
],
```

#### Environment Variables

```env
MODEL_CACHE_SEARCH_INDEX_ENABLED=true
MODEL_CACHE_SEARCH_INDEX_DRIVER=pgsql
MODEL_CACHE_SEARCH_INDEX_CONNECTION=pgsql_search
```

## Kullanım

### Model Yapılandırması

```php
use Snowsoft\LaravelModelCaching\CachedModel;

class Product extends CachedModel
{
    // Arama yapılacak kolonları tanımla
    protected $searchable = ['name', 'description', 'sku'];
}
```

### Otomatik Index Yönetimi

Model oluşturma, güncelleme ve silme işlemlerinde index otomatik olarak güncellenir:

```php
// Yeni kayıt - index otomatik oluşturulur
$product = Product::create([
    'name' => 'Laptop',
    'description' => 'Gaming laptop',
    'sku' => 'LAP-001',
]);

// Güncelleme - index otomatik güncellenir
$product->update(['name' => 'Gaming Laptop']);

// Silme - index otomatik silinir
$product->delete();
```

### Arama

Index servisi aktifse, arama sorguları index'ten yapılır:

```php
// Index'ten arama yapılır (eğer aktifse)
$products = Product::search('laptop')->get();

// Index servisi aktif değilse normal LIKE sorgusu kullanılır
```

### Index Rebuild

Tüm model'ler için index'i yeniden oluşturma:

```php
use Snowsoft\LaravelModelCaching\Services\SearchIndexService;

$service = app(SearchIndexService::class);

// Tüm Product kayıtları için index'i yeniden oluştur
$count = $service->rebuildIndex(Product::class);
echo "Indexed {$count} records";
```

## MongoDB Detayları

### Index Yapısı

MongoDB'de her model için ayrı bir collection oluşturulur:

```
search_index_App_Models_Product
```

### Document Yapısı

```json
{
  "model_id": 1,
  "model_type": "App\\Models\\Product",
  "data": {
    "name": "Laptop",
    "description": "Gaming laptop",
    "sku": "LAP-001"
  },
  "search_text": "Laptop Gaming laptop LAP-001",
  "updated_at": "2024-01-01T00:00:00Z"
}
```

### Text Index

MongoDB text index otomatik olarak oluşturulur:

```javascript
db.search_index_App_Models_Product.createIndex({
  "name": "text",
  "description": "text",
  "sku": "text"
}, {
  name: "search_text_index"
})
```

### Arama

MongoDB text search kullanılır:

```javascript
db.search_index_App_Models_Product.find({
  $text: { $search: "laptop" },
  model_type: "App\\Models\\Product"
})
```

## PostgreSQL Detayları

### Tablo Yapısı

PostgreSQL'de her model için ayrı bir tablo oluşturulur:

```sql
CREATE TABLE search_index_app_models_product (
    id SERIAL PRIMARY KEY,
    model_id INTEGER NOT NULL,
    model_type VARCHAR(255) NOT NULL,
    search_text TSVECTOR,
    data JSONB,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE(model_id, model_type)
);
```

### GIN Index

Full-text search için GIN index oluşturulur:

```sql
CREATE INDEX search_index_app_models_product_search_text_idx
ON search_index_app_models_product USING GIN(search_text);
```

### TSVECTOR

Arama metni otomatik olarak tsvector'e dönüştürülür:

```sql
SELECT to_tsvector('english', 'Laptop Gaming laptop LAP-001');
-- 'gaming':2 'laptop':1,3 'lap':4 '001':5
```

### Arama

PostgreSQL full-text search kullanılır:

```sql
SELECT model_id
FROM search_index_app_models_product
WHERE model_type = 'App\Models\Product'
AND search_text @@ to_tsquery('english', 'laptop')
ORDER BY ts_rank(search_text, to_tsquery('english', 'laptop')) DESC;
```

## API Referansı

### SearchIndexService

#### `isEnabled(): bool`

Index servisi aktif mi kontrol eder.

```php
$service = app(SearchIndexService::class);
if ($service->isEnabled()) {
    // Index servisi aktif
}
```

#### `createIndex(Model $model, array $searchableColumns = []): bool`

Model için index oluşturur.

```php
$service->createIndex($product, ['name', 'description']);
```

#### `updateIndex(Model $model, array $data): bool`

Model için index'i günceller.

```php
$service->updateIndex($product, $product->toArray());
```

#### `deleteIndex(Model $model): bool`

Model için index'i siler.

```php
$service->deleteIndex($product);
```

#### `searchIndex(Model $model, string $term, array $searchableColumns = []): array`

Index'ten arama yapar ve model ID'lerini döndürür.

```php
$ids = $service->searchIndex($product, 'laptop', ['name', 'description']);
// [1, 5, 10, ...]
```

#### `rebuildIndex(string $modelClass): int`

Tüm model'ler için index'i yeniden oluşturur.

```php
$count = $service->rebuildIndex(Product::class);
```

## Performans

### MongoDB

- ✅ Text index ile hızlı arama
- ✅ Regex fallback desteği
- ⚠️ Collection bazlı ayrım

### PostgreSQL

- ✅ TSVECTOR ile güçlü full-text search
- ✅ Relevance scoring (ts_rank)
- ✅ GIN index ile hızlı arama
- ⚠️ Tablo bazlı ayrım

## Sınırlamalar

1. **Production Dışında:** Bu özellik sadece development/test ortamlarında çalışır
2. **Searchable Columns:** Model'de `$searchable` property'si tanımlı olmalı
3. **Connection Gereksinimi:** MongoDB veya PostgreSQL connection yapılandırılmış olmalı
4. **Index Senkronizasyonu:** Manuel index güncellemeleri gerekebilir

## Troubleshooting

### Index Oluşturulmuyor

**Problem:** Index oluşturulmuyor
**Çözüm:**
- Environment variable'ları kontrol edin
- Connection yapılandırmasını kontrol edin
- Log dosyalarını kontrol edin

### Arama Sonuç Vermiyor

**Problem:** Index'ten arama sonuç vermiyor
**Çözüm:**
- Index'in oluşturulduğundan emin olun
- `rebuildIndex()` ile index'i yeniden oluşturun
- Fallback LIKE sorgularına geri döner

### MongoDB Connection Hatası

**Problem:** MongoDB connection hatası
**Çözüm:**
- MongoDB servisinin çalıştığından emin olun
- Connection bilgilerini kontrol edin
- `mongodb/laravel-mongodb` paketinin kurulu olduğundan emin olun

### PostgreSQL TSVECTOR Hatası

**Problem:** PostgreSQL tsvector hatası
**Çözüm:**
- PostgreSQL'in full-text search desteğinin aktif olduğundan emin olun
- Connection bilgilerini kontrol edin
- Tablo ve index'lerin oluşturulduğundan emin olun

## Örnek Senaryolar

### Senaryo 1: Development Ortamında MongoDB Index

```env
APP_ENV=local
MODEL_CACHE_SEARCH_INDEX_ENABLED=true
MODEL_CACHE_SEARCH_INDEX_DRIVER=mongodb
MODEL_CACHE_SEARCH_INDEX_CONNECTION=mongodb
```

```php
class Product extends CachedModel
{
    protected $searchable = ['name', 'description'];
}

// Otomatik index oluşturulur ve güncellenir
$product = Product::create(['name' => 'Laptop', 'description' => 'Gaming']);

// Index'ten arama
$products = Product::search('laptop')->get();
```

### Senaryo 2: Test Ortamında PostgreSQL Index

```env
APP_ENV=testing
MODEL_CACHE_SEARCH_INDEX_ENABLED=true
MODEL_CACHE_SEARCH_INDEX_DRIVER=pgsql
MODEL_CACHE_SEARCH_INDEX_CONNECTION=pgsql_search
```

```php
// Test sırasında index rebuild
$service = app(SearchIndexService::class);
$service->rebuildIndex(Product::class);
```

## Daha Fazla Bilgi

- [MongoDB Laravel Package](https://github.com/mongodb/laravel-mongodb)
- [PostgreSQL Full-Text Search](https://www.postgresql.org/docs/current/textsearch.html)
- [SEARCH_QUERY_UPDATE_FEATURES.md](SEARCH_QUERY_UPDATE_FEATURES.md) - Arama özellikleri
