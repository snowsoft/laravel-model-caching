# Güvenlik ve Performans İyileştirme Önerileri

Bu dokümantasyon, Laravel Model Caching paketi için önerilen güvenlik ve performans iyileştirmelerini içerir.

## Güvenlik İyileştirmeleri

### 1. Cache Key Sanitization ve Validation

**Sorun:** Cache key'lerde kullanıcı girdileri doğrudan kullanılıyor, sanitization eksik.

**Öneri:**
- Tüm cache key bileşenlerini sanitize et
- Özel karakterleri temizle veya encode et
- Cache key length limiti ekle
- Hash kullanarak uzun key'leri kısalt

**Uygulama:**
```php
// src/CacheKey.php
protected function sanitizeKeyComponent(string $component): string
{
    // Null bytes'ı temizle
    $component = str_replace("\0", '', $component);

    // Özel karakterleri temizle
    $component = preg_replace('/[^a-zA-Z0-9_\-:]/', '_', $component);

    // Length limiti
    if (strlen($component) > 250) {
        $component = substr($component, 0, 250);
    }

    return $component;
}

public function make(...): string
{
    $key = $this->getCachePrefix();
    $key .= $this->sanitizeKeyComponent($this->getTableSlug());
    // ... diğer bileşenler

    // Uzun key'leri hash'le
    if (strlen($key) > 250) {
        $key = substr($key, 0, 200) . ':' . hash('sha256', $key);
    }

    return $key;
}
```

### 2. Tenant ID Validation

**Sorun:** Tenant ID'ler validate edilmiyor, injection riski var.

**Öneri:**
- Tenant ID'leri validate et
- Sadece alphanumeric ve belirli karakterlere izin ver
- Length limiti ekle

**Uygulama:**
```php
// src/TenantResolver.php
public static function getTenantId(): ?string
{
    $tenantId = // ... mevcut kod

    if ($tenantId) {
        $tenantId = self::validateTenantId($tenantId);
    }

    return $tenantId;
}

protected static function validateTenantId(string $tenantId): ?string
{
    // Sadece alphanumeric, dash, underscore
    if (!preg_match('/^[a-zA-Z0-9_-]{1,64}$/', $tenantId)) {
        \Log::warning('Invalid tenant ID format', ['tenant_id' => $tenantId]);
        return null;
    }

    return $tenantId;
}
```

### 3. Redis keys() Komutu Yerine SCAN Kullanımı

**Sorun:** `redis->keys()` komutu production'da blocking ve tehlikeli.

**Öneri:**
- `keys()` yerine `scan()` kullan
- Batch processing ekle
- Timeout ekle

**Uygulama:**
```php
// src/Services/SelectiveCacheInvalidator.php
protected function invalidateByPattern(Model $model): void
{
    // ... mevcut kod

    if (method_exists($this->cache->getStore(), 'getRedis')) {
        $redis = $this->cache->getStore()->getRedis();

        // keys() yerine SCAN kullan
        $cursor = 0;
        $keys = [];

        do {
            $result = $redis->scan($cursor, [
                'MATCH' => $pattern,
                'COUNT' => 100, // Batch size
            ]);

            $cursor = $result[0];
            $keys = array_merge($keys, $result[1]);

            // Timeout koruması
            if (count($keys) > 10000) {
                \Log::warning('Too many cache keys to invalidate', ['pattern' => $pattern]);
                break;
            }
        } while ($cursor !== 0);

        // Batch delete
        if (!empty($keys)) {
            $redis->del($keys);
        }
    }
}
```

### 4. Exception Handling İyileştirmesi

**Sorun:** "Silently fail" kullanımı güvenlik riski oluşturuyor.

**Öneri:**
- Exception'ları log'la
- Kritik hataları throw et
- Error tracking ekle

**Uygulama:**
```php
// src/Services/SelectiveCacheInvalidator.php
protected function invalidateModelCache(Model $model): void
{
    try {
        // ... mevcut kod
    } catch (\Exception $e) {
        // Log instead of silently fail
        \Log::error('Cache invalidation failed', [
            'model' => get_class($model),
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString(),
        ]);

        // Re-throw critical errors
        if ($e instanceof \RedisException || $e instanceof \CacheException) {
            throw $e;
        }
    }
}
```

### 5. Cache Key Collision Prevention

**Sorun:** Farklı query'ler aynı cache key'i üretebilir.

**Öneri:**
- Cache key'lerde hash kullan
- Query fingerprint ekle
- Collision detection ekle

**Uygulama:**
```php
// src/CacheKey.php
public function make(...): string
{
    // ... mevcut key oluşturma

    // Hash ekle collision prevention için
    $fingerprint = hash('sha256', serialize([
        $this->eagerLoad,
        $this->query->toSql(),
        $this->query->getBindings(),
        $this->withoutGlobalScopes,
    ]));

    $key .= ':' . substr($fingerprint, 0, 16);

    return $key;
}
```

## Performans İyileştirmeleri

### 1. Reflection Kullanımını Optimize Et

**Sorun:** SelectiveCacheInvalidator'da her seferinde reflection kullanılıyor.

**Öneri:**
- Reflection sonuçlarını cache'le
- Model relationship'lerini önceden tespit et
- Static cache kullan

**Uygulama:**
```php
// src/Services/SelectiveCacheInvalidator.php
protected static $relationCache = [];

protected function invalidateBelongsToRelations(Model $model): void
{
    $modelClass = get_class($model);

    // Cache'den al
    if (!isset(self::$relationCache[$modelClass])) {
        self::$relationCache[$modelClass] = $this->detectRelations($model, \Illuminate\Database\Eloquent\Relations\BelongsTo::class);
    }

    foreach (self::$relationCache[$modelClass] as $relationName) {
        try {
            $relation = $model->{$relationName}();
            $related = $relation->getRelated();

            if (method_exists($related, 'flushCache')) {
                $related->flushCache();
            }
        } catch (\Exception $e) {
            \Log::warning('Failed to invalidate relation cache', [
                'model' => $modelClass,
                'relation' => $relationName,
                'error' => $e->getMessage(),
            ]);
        }
    }
}

protected function detectRelations(Model $model, string $relationType): array
{
    $relations = [];
    $reflection = new \ReflectionClass($model);

    foreach ($reflection->getMethods(\ReflectionMethod::IS_PUBLIC) as $method) {
        if ($method->getNumberOfParameters() === 0) {
            try {
                $relation = $model->{$method->getName()}();
                if ($relation instanceof $relationType) {
                    $relations[] = $method->getName();
                }
            } catch (\Exception $e) {
                // Skip
            }
        }
    }

    return $relations;
}
```

### 2. Cache Key Generation Optimizasyonu

**Sorun:** String concatenation yerine daha verimli yöntemler kullanılabilir.

**Öneri:**
- Array join kullan
- StringBuilder pattern
- Key component'leri cache'le

**Uygulama:**
```php
// src/CacheKey.php
protected static $keyComponentCache = [];

public function make(...): string
{
    $components = [];
    $components[] = $this->getCachePrefix();
    $components[] = $this->getTableSlug();
    $components[] = $this->getModelSlug();
    // ... diğer bileşenler

    // Array join daha hızlı
    $key = implode('', $components);

    // Hash if too long
    if (strlen($key) > 250) {
        $key = substr($key, 0, 200) . ':' . hash('sha256', $key);
    }

    return $key;
}
```

### 3. Cache Prefix Caching

**Sorun:** Cache prefix her seferinde hesaplanıyor.

**Öneri:**
- Cache prefix'i cache'le
- Static property kullan
- Sadece config değiştiğinde yeniden hesapla

**Uygulama:**
```php
// src/Traits/CachePrefixing.php
protected static $prefixCache = [];
protected static $configHash = null;

protected function getCachePrefix(): string
{
    $config = Container::getInstance()->make("config");
    $currentHash = md5(serialize([
        $config->get("laravel-model-caching.cache-prefix", ""),
        $config->get("laravel-model-caching.use-database-keying"),
        TenantResolver::isEnabled() ? TenantResolver::getTenantId() : null,
    ]));

    // Config değişmediyse cache'den al
    if (self::$configHash === $currentHash && isset(self::$prefixCache[$currentHash])) {
        return self::$prefixCache[$currentHash];
    }

    // Yeni prefix hesapla
    $prefix = "snowsoft:laravel-model-caching:";

    // ... mevcut prefix oluşturma kodu

    // Cache'le
    self::$configHash = $currentHash;
    self::$prefixCache[$currentHash] = $prefix;

    return $prefix;
}
```

### 4. Batch Cache Operations

**Sorun:** Her cache işlemi ayrı ayrı yapılıyor.

**Öneri:**
- Batch operations kullan
- Pipeline kullan (Redis)
- Transaction kullan

**Uygulama:**
```php
// src/Services/SelectiveCacheInvalidator.php
protected function invalidateModelCache(Model $model): void
{
    // ... mevcut kod

    // Batch operations için
    if (method_exists($cache->getStore(), 'getRedis')) {
        $redis = $cache->getStore()->getRedis();

        // Pipeline kullan
        $pipeline = $redis->pipeline();

        foreach ($keysToInvalidate as $key) {
            $pipeline->del($key);
        }

        $pipeline->execute();
    }
}
```

### 5. Lazy Loading Optimization

**Sorun:** Cache invalidation'da tüm relation'lar kontrol ediliyor.

**Öneri:**
- Sadece cached relation'ları invalidate et
- Lazy evaluation kullan
- Relation cache'i optimize et

**Uygulama:**
```php
// src/Services/SelectiveCacheInvalidator.php
protected function invalidateRelatedCaches(Model $model): void
{
    // Sadece cached relation'ları al
    $cachedRelations = $this->getCachedRelations($model);

    foreach ($cachedRelations as $relationName) {
        try {
            $relation = $model->{$relationName}();
            $related = $relation->getRelated();

            if (method_exists($related, 'flushCache')) {
                $related->flushCache();
            }
        } catch (\Exception $e) {
            // Log
        }
    }
}

protected function getCachedRelations(Model $model): array
{
    // Model'de cachedRelations property'si varsa kullan
    if (property_exists($model, 'cachedRelations')) {
        return $model->cachedRelations;
    }

    // Varsayılan: tüm relation'ları kontrol et
    return $this->detectAllRelations($model);
}
```

### 6. Memory Optimization

**Sorun:** Büyük result set'lerde memory kullanımı yüksek.

**Öneri:**
- Chunk processing
- Generator kullan
- Memory limit kontrolü

**Uygulama:**
```php
// src/Traits/BuilderCaching.php
public function get($columns = ['*'])
{
    // Büyük result set'ler için chunk
    if ($this->query->limit > 1000) {
        return $this->getChunked($columns);
    }

    // ... mevcut kod
}

protected function getChunked($columns)
{
    // Chunk'lar halinde cache'le
    $chunks = [];
    $this->query->chunk(1000, function ($items) use (&$chunks) {
        $chunks[] = $items;
    });

    return collect($chunks)->flatten();
}
```

## Öncelik Sırası

### Yüksek Öncelik (Güvenlik)
1. ✅ Cache key sanitization
2. ✅ Tenant ID validation
3. ✅ Redis keys() → SCAN değişikliği
4. ✅ Exception handling iyileştirmesi

### Orta Öncelik (Performans)
1. ✅ Reflection optimization
2. ✅ Cache prefix caching
3. ✅ Batch cache operations

### Düşük Öncelik (Optimizasyon)
1. ✅ Cache key generation optimization
2. ✅ Lazy loading optimization
3. ✅ Memory optimization

## Uygulama Notları

1. **Backward Compatibility:** Tüm değişiklikler backward compatible olmalı
2. **Testing:** Her iyileştirme için test yazılmalı
3. **Documentation:** Değişiklikler dokümante edilmeli
4. **Performance Metrics:** İyileştirmeler ölçülmeli
5. **Gradual Rollout:** Production'a aşamalı olarak deploy edilmeli

## Test Senaryoları

Her iyileştirme için test senaryoları:

1. **Güvenlik Testleri:**
   - Injection denemeleri
   - Tenant isolation
   - Cache poisoning
   - Key collision

2. **Performans Testleri:**
   - Cache hit/miss rates
   - Memory usage
   - Query execution time
   - Concurrent access

3. **Regression Testleri:**
   - Mevcut functionality
   - Edge cases
   - Error handling
