# PostgreSQL ve MongoDB Desteği

Bu dokümantasyon, Laravel Model Caching paketinin PostgreSQL ve MongoDB ile nasıl kullanılacağını açıklar.

## PostgreSQL Desteği

PostgreSQL desteği **tam olarak desteklenmektedir** ve test edilmiştir.

### Kurulum

1. PostgreSQL bağlantısını `config/database.php` dosyasında yapılandırın:

```php
'connections' => [
    'pgsql' => [
        'driver' => 'pgsql',
        'host' => env('DB_HOST', '127.0.0.1'),
        'port' => env('DB_PORT', '5432'),
        'database' => env('DB_DATABASE', 'forge'),
        'username' => env('DB_USERNAME', 'forge'),
        'password' => env('DB_PASSWORD', ''),
        'charset' => 'utf8',
        'prefix' => '',
        'prefix_indexes' => true,
        'schema' => 'public',
        'sslmode' => 'prefer',
    ],
],
```

2. Model'e `Cachable` trait'ini ekleyin:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Snowsoft\LaravelModelCaching\Traits\Cachable;

class Product extends Model
{
    use Cachable;

    protected $connection = 'pgsql';

    protected $table = 'products';
}
```

### PostgreSQL Özel Cache Store

PostgreSQL için ayrı bir cache store kullanmak isterseniz:

```php
// config/laravel-model-caching.php
'connection-stores' => [
    'pgsql' => 'redis_pgsql', // PostgreSQL için özel Redis store
],
```

Veya `.env` dosyasında:

```env
MODEL_CACHE_CONNECTION_STORES={"pgsql":"redis_pgsql","mysql":"redis_mysql"}
```

`config/cache.php` dosyasında store'u tanımlayın:

```php
'stores' => [
    'redis_pgsql' => [
        'driver' => 'redis',
        'connection' => 'pgsql_cache',
    ],
],
```

### PostgreSQL JSON Sorguları

PostgreSQL'in JSON desteği ile birlikte cache'leme:

```php
// JSON içinde arama - otomatik cache'lenir
$products = Product::whereJsonContains('metadata->tags', 'electronics')->get();

// JSON path sorguları
$products = Product::whereJsonLength('metadata->tags', '>', 3)->get();
```

## MongoDB Desteği

MongoDB desteği, Laravel MongoDB paketleri ile birlikte çalışır. Teorik olarak desteklenir, ancak test edilmesi önerilir.

### Gereksinimler

MongoDB için Laravel'de şu paketlerden birini kullanmanız gerekir:

1. **jenssegers/mongodb** (Önerilen)
2. **mongodb/laravel-mongodb**

### Kurulum

1. MongoDB paketini yükleyin:

```bash
composer require jenssegers/mongodb
```

2. `config/database.php` dosyasında MongoDB bağlantısını yapılandırın:

```php
'connections' => [
    'mongodb' => [
        'driver' => 'mongodb',
        'host' => env('MONGO_DB_HOST', 'localhost'),
        'port' => env('MONGO_DB_PORT', 27017),
        'database' => env('MONGO_DB_DATABASE', 'laravel'),
        'username' => env('MONGO_DB_USERNAME', ''),
        'password' => env('MONGO_DB_PASSWORD', ''),
        'options' => [
            'database' => env('MONGO_DB_AUTHENTICATION_DATABASE', 'admin'),
        ],
    ],
],
```

3. Model'i MongoDB için yapılandırın:

```php
<?php

namespace App\Models;

use Jenssegers\Mongodb\Eloquent\Model;
use Snowsoft\LaravelModelCaching\Traits\Cachable;

class Product extends Model
{
    use Cachable;

    protected $connection = 'mongodb';
    protected $collection = 'products';
}
```

### MongoDB Özel Cache Store

MongoDB için ayrı bir cache store kullanmak:

```php
// config/laravel-model-caching.php
'connection-stores' => [
    'mongodb' => 'redis_mongodb',
],
```

### MongoDB Özel Durumlar

#### 1. ObjectId Desteği

MongoDB ObjectId'leri string'e çevrilir, cache key'lerde sorun olmaz.

#### 2. Embedded Documents

Embedded document'ler normal ilişkiler gibi cache'lenir:

```php
class Product extends Model
{
    use Cachable;

    public function reviews()
    {
        return $this->embedsMany(Review::class);
    }
}

// Cache'lenir
$product = Product::with('reviews')->find($id);
```

#### 3. Collection vs Table

MongoDB'de `$table` yerine `$collection` kullanılır, ancak paket bunu otomatik olarak algılar.

### MongoDB Test Örneği

```php
<?php

namespace Tests\Integration;

use App\Models\Product;
use Tests\IntegrationTestCase;

class MongoDbCachingTest extends IntegrationTestCase
{
    public function testMongoDbModelCaching()
    {
        // Model oluştur
        $product = Product::create([
            'name' => 'Test Product',
            'price' => 99.99,
        ]);

        // İlk sorgu - cache'e yazılır
        $first = Product::where('name', 'Test Product')->first();
        $this->assertNotNull($first);

        // İkinci sorgu - cache'den okunur
        $second = Product::where('name', 'Test Product')->first();
        $this->assertEquals($first->id, $second->id);

        // Cache temizle
        Product::flushCache();

        // Üçüncü sorgu - tekrar DB'den okunur
        $third = Product::where('name', 'Test Product')->first();
        $this->assertNotNull($third);
    }
}
```

## Çoklu Veritabanı Kullanımı

Hem PostgreSQL hem MongoDB'yi aynı projede kullanabilirsiniz:

### Konfigürasyon

```php
// config/laravel-model-caching.php
'connection-stores' => [
    'mysql' => 'redis_mysql',
    'pgsql' => 'redis_pgsql',
    'mongodb' => 'redis_mongodb',
],
```

### Modeller

```php
// PostgreSQL Model
class User extends Model
{
    use Cachable;
    protected $connection = 'pgsql';
}

// MongoDB Model
class Log extends Model
{
    use Cachable;
    protected $connection = 'mongodb';
}
```

### Cache Key Yapısı

Her connection için ayrı cache key prefix'i kullanılır:

```
snowsoft:laravel-model-caching:pgsql:database_name:users:...
snowsoft:laravel-model-caching:mongodb:database_name:logs:...
```

## Sorun Giderme

### PostgreSQL

1. **JSON sorguları cache'lenmiyor**: PostgreSQL JSON operatörleri desteklenir, cache key'lerde sorun olmamalı.

2. **Array tipi sorunları**: PostgreSQL array tipleri string'e çevrilir, cache key'lerde sorun olmaz.

### MongoDB

1. **ObjectId sorunları**: ObjectId'ler otomatik olarak string'e çevrilir.

2. **Embedded relations**: `embedsMany` ve `embedsOne` ilişkileri normal ilişkiler gibi cache'lenir.

3. **Collection adları**: `$collection` property'si `$table` yerine kullanılır, paket bunu algılar.

4. **Connection name**: MongoDB connection'ı `mongodb` olarak tanımlanmalıdır.

## Performans Notları

1. **PostgreSQL**: JSON sorguları cache'lendiğinde önemli performans artışı sağlar.

2. **MongoDB**: Büyük collection'larda cache kullanımı özellikle faydalıdır.

3. **Çoklu DB**: Her connection için ayrı cache store kullanmak, cache izolasyonu sağlar.

## Örnek Kullanım Senaryoları

### Senaryo 1: PostgreSQL + Redis

```php
// .env
DB_CONNECTION=pgsql
MODEL_CACHE_CONNECTION_STORES={"pgsql":"redis"}

// Model
class Product extends Model
{
    use Cachable;
    protected $connection = 'pgsql';
}
```

### Senaryo 2: MongoDB + Redis

```php
// .env
MONGO_DB_CONNECTION=mongodb
MODEL_CACHE_CONNECTION_STORES={"mongodb":"redis"}

// Model
class Log extends Model
{
    use Cachable;
    protected $connection = 'mongodb';
}
```

### Senaryo 3: Hybrid (PostgreSQL + MongoDB)

```php
// .env
MODEL_CACHE_CONNECTION_STORES={"pgsql":"redis_pgsql","mongodb":"redis_mongodb"}

// PostgreSQL Model
class User extends Model
{
    use Cachable;
    protected $connection = 'pgsql';
}

// MongoDB Model
class ActivityLog extends Model
{
    use Cachable;
    protected $connection = 'mongodb';
}
```

## Test Etme

Her iki veritabanı için de test yazabilirsiniz:

```php
public function testPostgresqlCaching()
{
    $product = Product::create(['name' => 'Test']);
    $cached = Product::find($product->id);
    $this->assertNotNull($cached);
}

public function testMongoDbCaching()
{
    $log = ActivityLog::create(['action' => 'test']);
    $cached = ActivityLog::find($log->id);
    $this->assertNotNull($cached);
}
```
