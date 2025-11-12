<?php

namespace Snowsoft\LaravelModelCaching\Services;

use Illuminate\Container\Container;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

/**
 * Search Cache Service
 *
 * Arama sorguları için özel cache yönetimi
 */
class SearchCacheService
{
    protected $cache;
    protected $config;

    public function __construct()
    {
        $this->cache = Container::getInstance()->make('cache');
        $this->config = Container::getInstance()->make('config');
    }

    /**
     * Search query için cache key oluştur
     *
     * @param Model $model
     * @param string $term
     * @param array $columns
     * @param array $filters
     * @return string
     */
    public function getSearchCacheKey(Model $model, string $term, array $columns = [], array $filters = []): string
    {
        $key = 'search:' . get_class($model) . ':' . md5($term);

        if (!empty($columns)) {
            $key .= ':' . md5(implode(',', $columns));
        }

        if (!empty($filters)) {
            $key .= ':' . md5(serialize($filters));
        }

        return $key;
    }

    /**
     * Search result'ı cache'le
     *
     * @param Model $model
     * @param string $term
     * @param $results
     * @param array $columns
     * @param array $filters
     * @param int|null $ttl
     * @return void
     */
    public function cacheSearchResult(Model $model, string $term, $results, array $columns = [], array $filters = [], ?int $ttl = null): void
    {
        $key = $this->getSearchCacheKey($model, $term, $columns, $filters);
        $tags = $this->getSearchCacheTags($model);

        $cache = $this->cache;
        if (method_exists($cache->getStore(), 'tags')) {
            $cache = $cache->tags($tags);
        }

        if ($ttl) {
            $cache->put($key, $results, $ttl);
        } else {
            $cache->forever($key, $results);
        }
    }

    /**
     * Search result'ı cache'den al
     *
     * @param Model $model
     * @param string $term
     * @param array $columns
     * @param array $filters
     * @return mixed|null
     */
    public function getCachedSearchResult(Model $model, string $term, array $columns = [], array $filters = [])
    {
        $key = $this->getSearchCacheKey($model, $term, $columns, $filters);
        $tags = $this->getSearchCacheTags($model);

        $cache = $this->cache;
        if (method_exists($cache->getStore(), 'tags')) {
            $cache = $cache->tags($tags);
        }

        return $cache->get($key);
    }

    /**
     * Search cache'i temizle
     *
     * @param Model $model
     * @param string|null $term
     * @return void
     */
    public function clearSearchCache(Model $model, ?string $term = null): void
    {
        $tags = $this->getSearchCacheTags($model);
        $cache = $this->cache;

        if (method_exists($cache->getStore(), 'tags')) {
            if ($term) {
                // Belirli bir search term için cache temizle
                $key = $this->getSearchCacheKey($model, $term);
                $cache->tags($tags)->forget($key);
            } else {
                // Tüm search cache'lerini temizle
                $cache->tags($tags)->flush();
            }
        } else {
            // Tag desteklenmiyorsa pattern ile temizle
            $pattern = 'search:' . get_class($model) . ':*';
            if ($term) {
                $pattern = 'search:' . get_class($model) . ':' . md5($term) . '*';
            }

            $this->clearByPattern($pattern);
        }
    }

    /**
     * Search cache tag'leri
     *
     * @param Model $model
     * @return array
     */
    protected function getSearchCacheTags(Model $model): array
    {
        $baseTag = 'search:' . str_replace('\\', '_', get_class($model));
        return [$baseTag, 'search'];
    }

    /**
     * Pattern'e göre cache temizle
     *
     * @param string $pattern
     * @return void
     */
    protected function clearByPattern(string $pattern): void
    {
        // Redis SCAN kullan (SelectiveCacheInvalidator'daki gibi)
        if (method_exists($this->cache->getStore(), 'getRedis')) {
            $redis = $this->cache->getStore()->getRedis();
            $keys = $this->scanKeys($redis, $pattern);

            if (!empty($keys)) {
                $redis->del($keys);
            }
        }
    }

    /**
     * SCAN kullanarak key'leri bul
     *
     * @param mixed $redis
     * @param string $pattern
     * @return array
     */
    protected function scanKeys($redis, string $pattern): array
    {
        $keys = [];
        $cursor = 0;

        do {
            try {
                if (method_exists($redis, 'scan')) {
                    $result = $redis->scan($cursor, [
                        'MATCH' => $pattern,
                        'COUNT' => 100,
                    ]);
                    $cursor = is_array($result) ? ($result[0] ?? 0) : 0;
                    $foundKeys = is_array($result) ? ($result[1] ?? []) : [];
                    $keys = array_merge($keys, $foundKeys);
                } else {
                    break;
                }
            } catch (\Exception $e) {
                break;
            }
        } while ($cursor !== 0 && $cursor !== '0');

        return array_unique($keys);
    }
}
