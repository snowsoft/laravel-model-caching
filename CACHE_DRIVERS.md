# Cache Driver Desteği

Bu dokümantasyon, Laravel Model Caching paketinin desteklediği cache driver'ları ve özel driver yapılandırmalarını açıklar.

## Standart Cache Driver'lar

### ✅ Önerilen (Taggable)

Bu driver'lar **tag desteği** sağlar ve **selective cache invalidation** ile çalışır:

#### 1. Redis (Önerilen)
```php
// config/cache.php
'stores' => [
    'redis' => [
        'driver' => 'redis',
        'connection' => 'cache',
    ],
],
```

**Avantajlar:**
- ✅ Tag desteği
- ✅ Yüksek performans
- ✅ Selective invalidation
- ✅ Production-ready

#### 2. Memcached
```php
// config/cache.php
'stores' => [
    'memcached' => [
        'driver' => 'memcached',
        'servers' => [
            [
                'host' => '127.0.0.1',
                'port' => 11211,
                'weight' => 100,
            ],
        ],
    ],
],
```

**Avantajlar:**
- ✅ Tag desteği
- ✅ Yüksek performans
- ✅ Selective invalidation

#### 3. APC/APCu
```php
// config/cache.php
'stores' => [
    'apc' => [
        'driver' => 'apc',
    ],
],
```

**Avantajlar:**
- ✅ Tag desteği
- ✅ PHP extension tabanlı
- ✅ Selective invalidation

#### 4. Array (Test için)
```php
// config/cache.php
'stores' => [
    'array' => [
        'driver' => 'array',
    ],
],
```

**Kullanım:**
- ✅ Sadece test ortamı için
- ✅ Tag desteği
- ✅ Memory-based

### ⚠️ Önerilmeyen (Non-taggable)

Bu driver'lar **tag desteği sağlamaz** ve **tüm cache'i temizler**:

#### 1. Database
```php
// config/cache.php
'stores' => [
    'database' => [
        'driver' => 'database',
        'table' => 'cache',
        'connection' => null,
    ],
],
```

**Notlar:**
- ❌ Tag desteği yok
- ❌ Tüm cache temizlenir (selective invalidation yok)
- ⚠️ Performans düşük
- ✅ PostgreSQL, MySQL, SQLite ile çalışır

**PostgreSQL ile Kullanım:**
```php
// config/cache.php
'stores' => [
    'pgsql_cache' => [
        'driver' => 'database',
        'table' => 'cache',
        'connection' => 'pgsql', // PostgreSQL connection
    ],
],
```

**Cache tablosunu oluşturun:**
```bash
php artisan cache:table
php artisan migrate
```

#### 2. File
```php
// config/cache.php
'stores' => [
    'file' => [
        'driver' => 'file',
        'path' => storage_path('framework/cache/data'),
    ],
],
```

**Notlar:**
- ❌ Tag desteği yok
- ❌ Tüm cache temizlenir
- ⚠️ Disk I/O yavaş
- ✅ Basit kurulum

#### 3. DynamoDB
```php
// config/cache.php
'stores' => [
    'dynamodb' => [
        'driver' => 'dynamodb',
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
        'table' => env('DYNAMODB_CACHE_TABLE', 'cache'),
        'endpoint' => env('DYNAMODB_ENDPOINT'),
    ],
],
```

**Notlar:**
- ❌ Tag desteği yok
- ❌ Tüm cache temizlenir
- ✅ AWS entegrasyonu

## Özel Cache Driver'lar

### MongoDB Cache Driver

MongoDB'yi cache driver olarak kullanmak için `mongodb/laravel-mongodb` paketi gerekir.

#### Kurulum

```bash
composer require mongodb/laravel-mongodb
```

#### Yapılandırma

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

// config/cache.php
'stores' => [
    'mongodb' => [
        'driver' => 'mongodb',
        'connection' => 'mongodb',
        'collection' => 'cache',
        'lock_connection' => 'mongodb',
        'lock_collection' => 'cache_locks',
        'lock_lottery' => [2, 100],
        'lock_timeout' => 86400,
    ],
],
```

#### Kullanım

```php
// config/laravel-model-caching.php
'connection-stores' => [
    'mongodb' => 'mongodb', // MongoDB connection için MongoDB cache driver
],

// veya .env
MODEL_CACHE_CONNECTION_STORES={"mongodb":"mongodb"}
```

**Notlar:**
- ⚠️ Tag desteği MongoDB cache driver'ında sınırlı olabilir
- ✅ MongoDB ile uyumlu
- ⚠️ Performans Redis/Memcached kadar yüksek değil

### PostgreSQL Database Cache Driver

PostgreSQL'i cache storage olarak kullanmak için Database cache driver'ını PostgreSQL connection ile yapılandırın:

```php
// config/database.php
'connections' => [
    'pgsql' => [
        'driver' => 'pgsql',
        'host' => env('DB_HOST', '127.0.0.1'),
        'port' => env('DB_PORT', '5432'),
        'database' => env('DB_DATABASE', 'forge'),
        'username' => env('DB_USERNAME', 'forge'),
        'password' => env('DB_PASSWORD', ''),
        // ...
    ],
],

// config/cache.php
'stores' => [
    'pgsql_cache' => [
        'driver' => 'database',
        'table' => 'cache',
        'connection' => 'pgsql', // PostgreSQL connection
    ],
],
```

#### Cache Tablosunu Oluşturma

```bash
php artisan cache:table
php artisan migrate
```

#### Kullanım

```php
// config/laravel-model-caching.php
'connection-stores' => [
    'pgsql' => 'pgsql_cache', // PostgreSQL connection için PostgreSQL cache
],

// veya .env
MODEL_CACHE_CONNECTION_STORES={"pgsql":"pgsql_cache"}
```

**Notlar:**
- ❌ Tag desteği yok (Database driver)
- ❌ Tüm cache temizlenir (selective invalidation yok)
- ⚠️ Performans düşük (disk-based)
- ✅ PostgreSQL ile uyumlu
- ⚠️ Production için önerilmez

## Connection-Specific Cache Stores

Her database connection için farklı cache store kullanabilirsiniz:

```php
// config/laravel-model-caching.php
'connection-stores' => [
    'mysql' => 'redis',           // MySQL için Redis
    'pgsql' => 'pgsql_cache',     // PostgreSQL için PostgreSQL cache
    'mongodb' => 'mongodb',       // MongoDB için MongoDB cache
    'sqlite' => 'file',           // SQLite için File cache
],
```

Veya `.env` dosyasında:

```env
MODEL_CACHE_CONNECTION_STORES={"mysql":"redis","pgsql":"pgsql_cache","mongodb":"mongodb"}
```

## Cache Driver Karşılaştırması

| Driver | Tag Desteği | Selective Invalidation | Performans | Production Ready |
|--------|-------------|----------------------|------------|------------------|
| Redis | ✅ | ✅ | ⭐⭐⭐⭐⭐ | ✅ |
| Memcached | ✅ | ✅ | ⭐⭐⭐⭐⭐ | ✅ |
| APC/APCu | ✅ | ✅ | ⭐⭐⭐⭐ | ✅ |
| Array | ✅ | ✅ | ⭐⭐⭐⭐⭐ | ❌ (Test only) |
| MongoDB | ⚠️ | ⚠️ | ⭐⭐⭐ | ⚠️ |
| Database (PostgreSQL) | ❌ | ❌ | ⭐⭐ | ⚠️ |
| Database (MySQL) | ❌ | ❌ | ⭐⭐ | ⚠️ |
| File | ❌ | ❌ | ⭐ | ❌ |
| DynamoDB | ❌ | ❌ | ⭐⭐⭐ | ✅ |

## Öneriler

### Production Ortamı
- ✅ **Redis** (en önerilen)
- ✅ **Memcached** (alternatif)
- ✅ **APC/APCu** (PHP extension tabanlı)

### Development Ortamı
- ✅ **Array** (test için)
- ✅ **File** (basit kurulum)

### Özel Senaryolar
- ⚠️ **MongoDB**: MongoDB ile uyumlu olması gerekiyorsa
- ⚠️ **PostgreSQL Database**: PostgreSQL'i cache storage olarak kullanmak istiyorsanız (performans düşük)
- ❌ **File/Database**: Production için önerilmez

## Tag Desteği ve Selective Invalidation

Tag desteği olan driver'lar:
- ✅ Redis
- ✅ Memcached
- ✅ APC/APCu
- ✅ Array

Tag desteği olmayan driver'lar:
- ❌ Database (PostgreSQL, MySQL, SQLite)
- ❌ File
- ❌ DynamoDB
- ⚠️ MongoDB (sınırlı)

**Önemli:** Tag desteği olmayan driver'larda, model güncellendiğinde **tüm cache temizlenir** (selective invalidation çalışmaz).

## Troubleshooting

### MongoDB Cache Driver Sorunları

**Problem:** MongoDB cache driver bulunamıyor
**Çözüm:**
```bash
composer require mongodb/laravel-mongodb
php artisan config:clear
```

**Problem:** Tag desteği çalışmıyor
**Çözüm:** MongoDB cache driver'ında tag desteği sınırlı olabilir. Redis veya Memcached kullanın.

### PostgreSQL Database Cache Sorunları

**Problem:** Cache tablosu yok
**Çözüm:**
```bash
php artisan cache:table
php artisan migrate
```

**Problem:** Selective invalidation çalışmıyor
**Çözüm:** Database driver tag desteği sağlamaz. Tüm cache temizlenir. Redis veya Memcached kullanın.

## Örnek Yapılandırmalar

### Senaryo 1: Multi-Database Multi-Cache

```php
// config/cache.php
'stores' => [
    'redis_mysql' => [
        'driver' => 'redis',
        'connection' => 'cache_mysql',
    ],
    'redis_pgsql' => [
        'driver' => 'redis',
        'connection' => 'cache_pgsql',
    ],
    'mongodb' => [
        'driver' => 'mongodb',
        'connection' => 'mongodb',
        'collection' => 'cache',
    ],
],

// config/laravel-model-caching.php
'connection-stores' => [
    'mysql' => 'redis_mysql',
    'pgsql' => 'redis_pgsql',
    'mongodb' => 'mongodb',
],
```

### Senaryo 2: PostgreSQL Database Cache

```php
// config/cache.php
'stores' => [
    'pgsql_cache' => [
        'driver' => 'database',
        'table' => 'cache',
        'connection' => 'pgsql',
    ],
],

// config/laravel-model-caching.php
'connection-stores' => [
    'pgsql' => 'pgsql_cache',
],
```

### Senaryo 3: MongoDB Cache

```php
// config/cache.php
'stores' => [
    'mongodb' => [
        'driver' => 'mongodb',
        'connection' => 'mongodb',
        'collection' => 'cache',
    ],
],

// config/laravel-model-caching.php
'connection-stores' => [
    'mongodb' => 'mongodb',
],
```

## Daha Fazla Bilgi

- [Laravel Cache Documentation](https://laravel.com/docs/cache)
- [MongoDB Laravel Package](https://github.com/mongodb/laravel-mongodb)
- [Redis Documentation](https://redis.io/docs/)
- [Memcached Documentation](https://memcached.org/)
