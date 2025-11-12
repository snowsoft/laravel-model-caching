# Multi-Tenancy ve Çoklu Veritabanı Cache Kullanım Kılavuzu

## Özellikler

Bu paket artık aşağıdaki özellikleri desteklemektedir:

1. **Multi-Tenancy Desteği**: Her tenant için ayrı cache key prefix'i
2. **Tenant-Specific Cache Stores**: Her tenant için ayrı cache store kullanımı
3. **Connection-Specific Cache Stores**: Her veritabanı bağlantısı için ayrı cache store
4. **Otomatik Tenant Resolver**: Popüler multi-tenancy paketleriyle uyumlu

## Konfigürasyon

### 1. Multi-Tenancy'yi Aktifleştirme

`.env` dosyanıza ekleyin:

```env
MODEL_CACHE_MULTI_TENANCY_ENABLED=true
MODEL_CACHE_TENANT_RESOLVER=fn() => auth()->user()->tenant_id
```

Veya `config/laravel-model-caching.php` dosyasında:

```php
'multi-tenancy' => [
    'enabled' => true,
    'tenant-resolver' => fn() => auth()->user()->tenant_id,
    'use-tenant-store' => false, // Her tenant için ayrı cache store kullanmak isterseniz true yapın
    'tenant-store-pattern' => 'tenant_{tenant_id}',
],
```

### 2. Tenant Resolver Özelleştirme

#### Yöntem 1: Config'de Closure

```php
'tenant-resolver' => fn() => request()->header('X-Tenant-ID'),
```

#### Yöntem 2: Kod İçinde

```php
use Snowsoft\LaravelModelCaching\TenantResolver;

TenantResolver::setResolver(function() {
    return auth()->user()->tenant_id;
});
```

#### Yöntem 3: Otomatik Tespit

Paket şu paketleri otomatik olarak destekler:
- `stancl/tenancy` (Laravel Tenancy)
- `spatie/laravel-multitenancy` (Spatie Multitenancy)
- Auth user'dan `tenant_id` alanı
- Request header'dan `X-Tenant-ID`

### 3. Her Tenant İçin Ayrı Cache Store

`config/cache.php` dosyanızda tenant-specific cache store'lar tanımlayın:

```php
'stores' => [
    'tenant_1' => [
        'driver' => 'redis',
        'connection' => 'tenant_1_cache',
        // ...
    ],
    'tenant_2' => [
        'driver' => 'redis',
        'connection' => 'tenant_2_cache',
        // ...
    ],
],
```

`.env` dosyasında:

```env
MODEL_CACHE_MULTI_TENANCY_ENABLED=true
MODEL_CACHE_USE_TENANT_STORE=true
MODEL_CACHE_TENANT_STORE_PATTERN=tenant_{tenant_id}
```

### 4. Her Veritabanı Bağlantısı İçin Ayrı Cache Store

`config/laravel-model-caching.php` dosyasında:

```php
'connection-stores' => [
    'mysql' => 'redis',
    'pgsql' => 'memcached',
    'tenant_db_1' => 'redis_tenant_1',
    'tenant_db_2' => 'redis_tenant_2',
],
```

Veya `.env` dosyasında JSON formatında:

```env
MODEL_CACHE_CONNECTION_STORES={"mysql":"redis","pgsql":"memcached","tenant_db_1":"redis_tenant_1"}
```

## Cache Store Öncelik Sırası

Cache store seçimi şu öncelik sırasına göre yapılır:

1. **Tenant-specific cache store** (eğer `use-tenant-store` aktifse)
2. **Connection-specific cache store** (eğer tanımlanmışsa)
3. **Global cache store** (`MODEL_CACHE_STORE` env değişkeni)
4. **Default cache store** (Laravel'in varsayılan cache store'u)

## Cache Key Yapısı

Multi-tenancy aktifken cache key'ler şu formatta oluşturulur:

```
snowsoft:laravel-model-caching:tenant:{tenant_id}:{connection}:{database}:{table}:{model}:{query_params}
```

Örnek:
```
snowsoft:laravel-model-caching:tenant:123:mysql:mydb:users:App\User:where_id_eq_1
```

## Kullanım Örnekleri

### Örnek 1: Basit Multi-Tenancy

```php
// .env
MODEL_CACHE_MULTI_TENANCY_ENABLED=true

// Model
use Snowsoft\LaravelModelCaching\Traits\Cachable;

class Product extends Model
{
    use Cachable;
}

// Kullanım - Her tenant için ayrı cache
$products = Product::where('status', 'active')->get();
// Cache key: snowsoft:laravel-model-caching:tenant:123:mysql:db:products:...
```

### Örnek 2: Tenant-Specific Cache Stores

```php
// config/cache.php
'stores' => [
    'tenant_1' => [
        'driver' => 'redis',
        'connection' => 'tenant_1_redis',
    ],
    'tenant_2' => [
        'driver' => 'redis',
        'connection' => 'tenant_2_redis',
    ],
],

// .env
MODEL_CACHE_MULTI_TENANCY_ENABLED=true
MODEL_CACHE_USE_TENANT_STORE=true
```

### Örnek 3: Connection-Specific Cache Stores

```php
// config/laravel-model-caching.php
'connection-stores' => [
    'read_db' => 'redis_read',
    'write_db' => 'redis_write',
],

// Model
class Product extends Model
{
    use Cachable;

    protected $connection = 'read_db'; // Bu connection için redis_read kullanılacak
}
```

### Örnek 4: Custom Tenant Resolver

```php
use Snowsoft\LaravelModelCaching\TenantResolver;

// AppServiceProvider'da
public function boot()
{
    TenantResolver::setResolver(function() {
        // Özel mantık
        if (request()->has('tenant')) {
            return request()->get('tenant');
        }

        if (session()->has('tenant_id')) {
            return session('tenant_id');
        }

        return null;
    });
}
```

## Cache Temizleme

### Tüm Tenant Cache'ini Temizleme

```php
use Snowsoft\LaravelModelCaching\TenantResolver;

$tenantId = TenantResolver::getTenantId();
// Tenant-specific cache otomatik olarak temizlenir
Product::flushCache();
```

### Belirli Bir Tenant'ın Cache'ini Temizleme

```php
// Tenant context'ini değiştirerek
TenantResolver::setResolver(fn() => 'specific_tenant_id');
Product::flushCache();
```

## Notlar

1. **Cache Tags**: Cache tag'leri de tenant-aware'dır, her tenant için ayrı tag'ler oluşturulur.

2. **Performance**: Multi-tenancy aktifken cache key'ler daha uzun olur, ancak bu tenant izolasyonu için gereklidir.

3. **Cache Store Requirements**: Tenant-specific cache store'lar kullanırken, her tenant için ayrı cache store tanımlamanız gerekir.

4. **Backward Compatibility**: Mevcut cache key'ler etkilenmez, sadece multi-tenancy aktifken yeni key'ler tenant-aware olur.
