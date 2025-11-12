# Performans Testleri

Bu dizin, Laravel Model Caching paketinin performans testlerini içerir.

## Test Kategorileri

### 1. CacheHitMissTest
Cache hit/miss oranlarını ve performans iyileştirmelerini ölçer:
- Cache hit oranlarının ölçülmesi
- İlk sorgu sonrası cache hit oranının yüksek olması
- Büyük dataset'lerde cache performansı
- İlişkilerle cache performansı
- Query sayısının azalması

### 2. MemoryUsageTest
Bellek kullanımını ölçer ve memory leak'leri tespit eder:
- Cache kullanımıyla bellek tüketimi
- Sınırsız bellek büyümesinin önlenmesi
- Cache flush sonrası bellek salınımı
- Büyük sonuç setlerinde bellek kullanımı
- İlişkilerle bellek kullanımı

### 3. ConcurrencyTest
Eşzamanlı erişim ve race condition testleri:
- Eşzamanlı cache yazma işlemleri
- Okuma sırasında yazma işlemleri
- Cache invalidation race condition'ları
- Çoklu tenant eşzamanlı erişimi
- Yük altında cache key generation

### 4. QueryPerformanceTest
Sorgu performans iyileştirmelerini ölçer:
- Basit sorgu performansı
- Karmaşık sorgu performansı
- Join sorgu performansı
- Aggregate sorgu performansı
- Pagination performansı
- Query sayısı azalması

## Performans Metrikleri

### Cache Hit Rate
Cache hit oranı %80'in üzerinde olmalıdır (ilk sorgu hariç).

### Speedup
Cache kullanımı en az 2x hızlandırma sağlamalıdır.

### Memory Usage
- 100 sorgu için < 50MB
- 1000 sorgu için < 100MB büyüme

### Query Reduction
Cache kullanımı DB sorgu sayısını önemli ölçüde azaltmalıdır.

## Çalıştırma

Tüm performans testlerini çalıştırmak için:

```bash
phpunit tests/Integration/Performance/
```

Belirli bir test sınıfını çalıştırmak için:

```bash
phpunit tests/Integration/Performance/CacheHitMissTest.php
```

## Performans İyileştirme Önerileri

1. **Cache Store Seçimi**: Redis veya Memcached gibi tag destekleyen cache store'lar kullanın.

2. **Selective Invalidation**: Sadece ilgili cache'leri temizleyin, tüm cache'i temizlemeyin.

3. **Cache Expiration**: Uygun cache expiration time'ları ayarlayın.

4. **Background Refresh**: Arka plan cache refresh'i kullanarak cache'i güncel tutun.

5. **Connection-Specific Stores**: Her veritabanı bağlantısı için ayrı cache store kullanın.

6. **Tenant-Specific Stores**: Multi-tenancy kullanıldığında tenant-specific store'lar kullanın.
