# Test Dokümantasyonu

Bu dokümantasyon, Laravel Model Caching paketinin test yapısını ve yeni eklenen güvenlik ve performans testlerini açıklar.

## Test Yapısı

```
tests/
├── Integration/
│   ├── Security/              # Güvenlik testleri (YENİ)
│   │   ├── CacheKeyInjectionTest.php
│   │   ├── TenantIsolationTest.php
│   │   ├── CachePoisoningTest.php
│   │   └── README.md
│   ├── Performance/           # Performans testleri (YENİ)
│   │   ├── CacheHitMissTest.php
│   │   ├── MemoryUsageTest.php
│   │   ├── ConcurrencyTest.php
│   │   ├── QueryPerformanceTest.php
│   │   └── README.md
│   └── ...                    # Mevcut testler
```

## Yeni Eklenen Testler

### Güvenlik Testleri

#### 1. CacheKeyInjectionTest
Cache key injection saldırılarını önlemek için testler:
- ✅ Özel karakterlerin güvenli işlenmesi (SQL injection, XSS, path traversal)
- ✅ Null byte injection
- ✅ Çok uzun string'ler
- ✅ Unicode karakterler
- ✅ Cache key collision önleme
- ✅ Array/Object injection

**Çalıştırma:**
```bash
phpunit tests/Integration/Security/CacheKeyInjectionTest.php
```

#### 2. TenantIsolationTest
Multi-tenancy güvenlik testleri:
- ✅ Tenant izolasyonu
- ✅ Cross-tenant cache erişiminin engellenmesi
- ✅ Tenant resolver injection önleme
- ✅ Tenant-specific cache store izolasyonu
- ✅ Cache flush işlemlerinin tenant'a özel olması

**Çalıştırma:**
```bash
phpunit tests/Integration/Security/TenantIsolationTest.php
```

#### 3. CachePoisoningTest
Cache poisoning saldırılarını önlemek için testler:
- ✅ Cache'in doğrudan manipüle edilememesi
- ✅ Cache invalidation'ın doğru çalışması
- ✅ Selective invalidation
- ✅ Cache key uniqueness
- ✅ Stale data önleme

**Çalıştırma:**
```bash
phpunit tests/Integration/Security/CachePoisoningTest.php
```

### Performans Testleri

#### 1. CacheHitMissTest
Cache hit/miss oranlarını ölçer:
- ✅ Cache hit oranı ölçümü
- ✅ İlk sorgu sonrası yüksek hit rate
- ✅ Büyük dataset'lerde performans
- ✅ İlişkilerle cache performansı
- ✅ Query sayısı azalması

**Çalıştırma:**
```bash
phpunit tests/Integration/Performance/CacheHitMissTest.php
```

#### 2. MemoryUsageTest
Bellek kullanımını ölçer:
- ✅ Cache kullanımıyla bellek tüketimi
- ✅ Sınırsız bellek büyümesinin önlenmesi
- ✅ Cache flush sonrası bellek salınımı
- ✅ Büyük sonuç setlerinde bellek kullanımı

**Çalıştırma:**
```bash
phpunit tests/Integration/Performance/MemoryUsageTest.php
```

#### 3. ConcurrencyTest
Eşzamanlı erişim testleri:
- ✅ Eşzamanlı cache yazma
- ✅ Okuma sırasında yazma
- ✅ Cache invalidation race condition'ları
- ✅ Çoklu tenant eşzamanlı erişimi
- ✅ Yük altında cache key generation

**Çalıştırma:**
```bash
phpunit tests/Integration/Performance/ConcurrencyTest.php
```

#### 4. QueryPerformanceTest
Sorgu performans iyileştirmelerini ölçer:
- ✅ Basit sorgu performansı
- ✅ Karmaşık sorgu performansı
- ✅ Join sorgu performansı
- ✅ Aggregate sorgu performansı
- ✅ Pagination performansı

**Çalıştırma:**
```bash
phpunit tests/Integration/Performance/QueryPerformanceTest.php
```

## Tüm Testleri Çalıştırma

### Tüm testler
```bash
phpunit
```

### Sadece güvenlik testleri
```bash
phpunit tests/Integration/Security/
```

### Sadece performans testleri
```bash
phpunit tests/Integration/Performance/
```

### Belirli bir test sınıfı
```bash
phpunit tests/Integration/Security/CacheKeyInjectionTest.php
```

### Belirli bir test metodu
```bash
phpunit tests/Integration/Security/CacheKeyInjectionTest.php::testCacheKeyWithSpecialCharacters
```

## Test Metrikleri

### Güvenlik Metrikleri
- ✅ Tüm injection denemeleri başarısız olmalı
- ✅ Tenant izolasyonu %100 sağlanmalı
- ✅ Cache poisoning denemeleri engellenmeli

### Performans Metrikleri
- ✅ Cache hit rate: > %80 (ilk sorgu hariç)
- ✅ Speedup: En az 2x hızlandırma
- ✅ Memory: 100 sorgu için < 50MB
- ✅ Query reduction: Önemli ölçüde azalma

## Test Gereksinimleri

- PHP 8.0+
- Laravel 8.0-12.0
- PHPUnit 10.5+
- Redis (performans testleri için önerilir)

## Test Ortamı

Testler için gerekli ortam değişkenleri `.env.testing` dosyasında tanımlanabilir:

```env
CACHE_DRIVER=redis
DB_CONNECTION=sqlite
DB_DATABASE=:memory:
```

## Yeni Test Ekleme

Yeni bir güvenlik testi eklemek için:

1. `tests/Integration/Security/` dizininde yeni bir test dosyası oluşturun
2. `IntegrationTestCase`'den extend edin
3. Test metodlarını `test` prefix'i ile başlatın
4. Dokümantasyonu güncelleyin

Yeni bir performans testi eklemek için:

1. `tests/Integration/Performance/` dizininde yeni bir test dosyası oluşturun
2. Performans metriklerini ölçün
3. Assertion'larda performans kriterlerini kontrol edin

## Sorun Giderme

### Testler çok yavaş çalışıyor
- Redis kullanıyorsanız, local Redis'in çalıştığından emin olun
- SQLite memory database kullanın
- Gereksiz test verilerini temizleyin

### Memory limit hatası
- `php.ini`'de `memory_limit` değerini artırın
- Test sonrası cache'i temizleyin
- Büyük dataset testlerini ayrı çalıştırın

### Tenant testleri başarısız
- Multi-tenancy config'inin doğru ayarlandığından emin olun
- Tenant resolver'ın doğru çalıştığından emin olun

## Katkıda Bulunma

Yeni testler eklerken:

1. Test'in amacını açıkça belirtin
2. Test metodlarını açıklayıcı isimlerle adlandırın
3. Assertion mesajlarını açıklayıcı yapın
4. Dokümantasyonu güncelleyin
5. Mevcut testlerle uyumlu olduğundan emin olun

## İletişim

Testlerle ilgili sorularınız için:
- GitHub Issues: [Repository Issues](https://github.com/snowsoft/laravel-model-caching/issues)
- Dokümantasyon: [README.md](README.md)
