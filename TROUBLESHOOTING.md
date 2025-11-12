# Troubleshooting Guide

Bu dokümantasyon, Laravel Model Caching paketi ile ilgili yaygın sorunları ve çözümlerini içerir.

## Yaygın Sorunlar

### 1. Cache Çalışmıyor

**Belirtiler:**
- Query'ler cache'lenmiyor
- Her seferinde database'e gidiyor

**Çözümler:**

1. **Cache'in aktif olduğundan emin olun:**
```bash
php artisan modelCache:health
```

2. **Config kontrolü:**
```env
MODEL_CACHE_ENABLED=true
```

3. **Cache store kontrolü:**
```php
// config/laravel-model-caching.php
'store' => 'redis', // veya 'memcached'
```

4. **Model'de trait kullanıldığından emin olun:**
```php
use Snowsoft\LaravelModelCaching\Traits\Cachable;

class Product extends Model
{
    use Cachable;
}
```

### 2. Cache Invalidation Çalışmıyor

**Belirtiler:**
- Model güncellendiğinde cache temizlenmiyor
- Eski veriler görünüyor

**Çözümler:**

1. **Selective invalidation kontrolü:**
```env
MODEL_CACHE_USE_SELECTIVE_INVALIDATION=true
```

2. **Cache store tag desteği:**
```php
// Redis veya Memcached kullanın (tag desteği var)
// Database veya File cache tag desteklemez
```

3. **Manuel cache temizleme:**
```php
Product::flushCache();
```

4. **Artisan command:**
```bash
php artisan modelCache:clear --model="App\Models\Product"
```

### 3. Multi-Tenancy Sorunları

**Belirtiler:**
- Tenant'lar birbirinin cache'ini görüyor
- Tenant ID resolve edilmiyor

**Çözümler:**

1. **Multi-tenancy aktif mi kontrol edin:**
```env
MODEL_CACHE_MULTI_TENANCY_ENABLED=true
```

2. **Tenant resolver kontrolü:**
```php
// config/laravel-model-caching.php
'tenant-resolver' => fn() => auth()->user()->tenant_id,
```

3. **Tenant ID validation:**
```php
// Tenant ID format kontrolü
TenantResolver::getTenantId(); // null dönüyorsa sorun var
```

4. **Tenant-specific store kullanın:**
```env
MODEL_CACHE_USE_TENANT_STORE=true
MODEL_CACHE_TENANT_STORE_PATTERN=tenant_{tenant_id}
```

### 4. Performance Sorunları

**Belirtiler:**
- Cache yavaş
- Memory kullanımı yüksek

**Çözümler:**

1. **Cache store seçimi:**
```php
// Redis önerilir (en hızlı)
'store' => 'redis',
```

2. **Selective invalidation kullanın:**
```env
MODEL_CACHE_USE_SELECTIVE_INVALIDATION=true
```

3. **cachedRelations property kullanın:**
```php
class Product extends Model
{
    use Cachable;

    // Sadece gerekli relation'ları tanımlayın
    protected $cachedRelations = ['category', 'tags'];
}
```

4. **Cache cooldown kullanın:**
```php
protected $cacheCooldownSeconds = 300; // 5 dakika
```

### 5. Redis Connection Sorunları

**Belirtiler:**
- Redis connection error
- Cache operations fail

**Çözümler:**

1. **Redis connection kontrolü:**
```bash
redis-cli ping
```

2. **Config kontrolü:**
```env
REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379
```

3. **Cache store config:**
```php
// config/cache.php
'redis' => [
    'driver' => 'redis',
    'connection' => 'default',
],
```

### 6. Cache Key Collision

**Belirtiler:**
- Farklı query'ler aynı sonucu döndürüyor
- Yanlış veriler cache'leniyor

**Çözümler:**

1. **Database keying kullanın:**
```env
MODEL_CACHE_USE_DATABASE_KEYING=true
```

2. **Cache prefix ekleyin:**
```php
// config/laravel-model-caching.php
'cache-prefix' => 'myapp',
```

3. **Model-specific prefix:**
```php
class Product extends Model
{
    use Cachable;

    protected $cachePrefix = 'products';
}
```

### 7. Memory Leaks

**Belirtiler:**
- Memory kullanımı sürekli artıyor
- Application yavaşlıyor

**Çözümler:**

1. **Cache expiration kullanın:**
```php
// Cache'ler otomatik expire olmalı
// rememberForever yerine remember kullanın (gerekirse)
```

2. **Cache temizleme:**
```bash
php artisan modelCache:clear
```

3. **Memory limit artırın:**
```ini
; php.ini
memory_limit = 256M
```

### 8. Test Environment Sorunları

**Belirtiler:**
- Test'lerde cache sorunları
- Test isolation problemi

**Çözümler:**

1. **Test'te cache'i temizleyin:**
```php
public function setUp(): void
{
    parent::setUp();
    Product::flushCache();
}
```

2. **Array cache kullanın:**
```php
// phpunit.xml
<env name="CACHE_DRIVER" value="array"/>
```

3. **Cache'i disable edin:**
```php
// Test'te
config(['laravel-model-caching.enabled' => false]);
```

## Debug Komutları

### Health Check
```bash
php artisan modelCache:health
```

### Statistics
```bash
php artisan modelCache:stats
php artisan modelCache:stats --model="App\Models\Product"
php artisan modelCache:stats --tenant=123
php artisan modelCache:stats --detailed
```

### Cache Clear
```bash
php artisan modelCache:clear
php artisan modelCache:clear --model="App\Models\Product"
```

## Debug Kodu

### Cache Key Debug
```php
$query = Product::where('status', 'active');
$key = $query->makeCacheKey(['*']);
dd($key);
```

### Cache Tags Debug
```php
$product = new Product();
$tags = $product->makeCacheTags();
dd($tags);
```

### Cache Store Debug
```php
$cache = app('cache');
$store = $cache->getStore();
dd(get_class($store));
```

## Log Kontrolü

### Cache Logging
```php
// config/logging.php
'channels' => [
    'cache' => [
        'driver' => 'single',
        'path' => storage_path('logs/cache.log'),
    ],
],
```

### Debug Mode
```env
APP_DEBUG=true
LOG_LEVEL=debug
```

## Performance Profiling

### Query Profiling
```php
DB::enableQueryLog();
Product::all();
dd(DB::getQueryLog());
```

### Cache Profiling
```php
$start = microtime(true);
$products = Product::all();
$time = microtime(true) - $start;
dd("Cache time: {$time}");
```

## Yardım Alma

### GitHub Issues
Sorununuzu GitHub'da açın:
- [Repository Issues](https://github.com/snowsoft/laravel-model-caching/issues)

### Debug Bilgileri Toplama
```bash
php artisan modelCache:health --detailed > health-report.txt
php artisan modelCache:stats --detailed > stats-report.txt
```

### Log Dosyaları
```bash
tail -f storage/logs/laravel.log
tail -f storage/logs/cache.log
```

## Sık Sorulan Sorular (FAQ)

### Q: Cache neden çalışmıyor?
A: `MODEL_CACHE_ENABLED=true` olduğundan ve model'de `Cachable` trait kullanıldığından emin olun.

### Q: Cache temizlenmiyor?
A: Cache store'un tag desteği olmalı (Redis/Memcached). Selective invalidation aktif olmalı.

### Q: Multi-tenancy çalışmıyor?
A: Tenant resolver doğru yapılandırılmış olmalı ve tenant ID validate edilmeli.

### Q: Performance sorunları?
A: Redis kullanın, selective invalidation aktif edin, `cachedRelations` property kullanın.

### Q: Test'lerde sorun?
A: Test'te array cache kullanın veya cache'i disable edin.

## İletişim

Sorunlarınız için:
- GitHub Issues: [Repository Issues](https://github.com/snowsoft/laravel-model-caching/issues)
- Dokümantasyon: [README.md](README.md)
