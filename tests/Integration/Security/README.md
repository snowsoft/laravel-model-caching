# Güvenlik Testleri

Bu dizin, Laravel Model Caching paketinin güvenlik testlerini içerir.

## Test Kategorileri

### 1. CacheKeyInjectionTest
Cache key injection saldırılarını önlemek için testler:
- Özel karakterlerin cache key'lerinde güvenli işlenmesi
- SQL injection denemelerinin cache key'lerini etkilememesi
- Unicode karakterlerin doğru işlenmesi
- Çok uzun string'lerin işlenmesi
- Cache key collision'ların önlenmesi

### 2. TenantIsolationTest
Multi-tenancy güvenlik testleri:
- Tenant izolasyonunun doğru çalışması
- Cross-tenant cache erişiminin engellenmesi
- Tenant resolver injection saldırılarının önlenmesi
- Tenant-specific cache store izolasyonu
- Cache flush işlemlerinin tenant'a özel olması

### 3. CachePoisoningTest
Cache poisoning saldırılarını önlemek için testler:
- Cache'in doğrudan manipüle edilememesi
- Cache invalidation'ın doğru çalışması
- Selective invalidation'ın cache poisoning'i önlemesi
- Cache key uniqueness'in sağlanması
- Stale data'nın önlenmesi

## Çalıştırma

Tüm güvenlik testlerini çalıştırmak için:

```bash
phpunit tests/Integration/Security/
```

Belirli bir test sınıfını çalıştırmak için:

```bash
phpunit tests/Integration/Security/CacheKeyInjectionTest.php
```

## Güvenlik Önerileri

1. **Cache Key Sanitization**: Tüm kullanıcı girdileri cache key'lerinde kullanılmadan önce sanitize edilmelidir.

2. **Tenant Isolation**: Multi-tenancy kullanıldığında, tenant ID'lerinin doğru validate edildiğinden emin olun.

3. **Cache Invalidation**: Model güncellemelerinde cache'in mutlaka invalidate edildiğinden emin olun.

4. **Selective Invalidation**: Sadece ilgili cache'lerin temizlendiğinden emin olun.

5. **Cache Expiration**: Cache'lerin uygun expiration time'ları olduğundan emin olun.
