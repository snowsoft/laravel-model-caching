<?php

namespace Snowsoft\LaravelModelCaching\Services;

use Illuminate\Container\Container;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Snowsoft\LaravelModelCaching\CacheTags;
use Snowsoft\LaravelModelCaching\CacheKey;

/**
 * Selective Cache Invalidator
 *
 * Sadece ilgili cache'leri temizler, tüm cache'i temizlemez.
 * Model event'lerinde kullanılır.
 */
class SelectiveCacheInvalidator
{
    protected $cache;

    public function __construct()
    {
        $this->cache = Container::getInstance()->make('cache');
    }

    /**
     * Model oluşturulduğunda ilgili cache'leri temizle
     */
    public function invalidateOnCreated(Model $model): void
    {
        $this->invalidateModelCache($model);
        $this->invalidateRelatedCaches($model);
    }

    /**
     * Model güncellendiğinde ilgili cache'leri temizle
     */
    public function invalidateOnUpdated(Model $model, array $dirtyAttributes = []): void
    {
        $this->invalidateModelCache($model);

        // Sadece değişen attribute'lara göre ilgili cache'leri temizle
        if (!empty($dirtyAttributes)) {
            $this->invalidateByAttributes($model, $dirtyAttributes);
        }

        $this->invalidateRelatedCaches($model);
    }

    /**
     * Model silindiğinde ilgili cache'leri temizle
     */
    public function invalidateOnDeleted(Model $model): void
    {
        $this->invalidateModelCache($model);
        $this->invalidateRelatedCaches($model);
    }

    /**
     * Belirli bir model için cache'leri temizle
     */
    protected function invalidateModelCache(Model $model): void
    {
        if (!method_exists($model, 'makeCacheTags')) {
            return;
        }

        try {
            $tags = $model->makeCacheTags();
            $cache = $model->cache($tags);

            // Tag'li cache varsa sadece o tag'leri temizle
            if (method_exists($cache->getStore(), 'tags')) {
                $cache->flush();
            } else {
                // Tag desteklenmiyorsa, model class'ına göre key pattern ile temizle
                $this->invalidateByPattern($model);
            }
        } catch (\RedisException $e) {
            // Redis hatalarını log'la ve re-throw et
            \Log::error('Redis error during cache invalidation', [
                'model' => get_class($model),
                'error' => $e->getMessage(),
            ]);
            throw $e;
        } catch (\Exception $e) {
            // Diğer hataları log'la ama devam et
            \Log::warning('Cache invalidation failed', [
                'model' => get_class($model),
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * İlgili modellerin cache'lerini temizle
     */
    protected function invalidateRelatedCaches(Model $model): void
    {
        // BelongsTo ilişkileri
        $this->invalidateBelongsToRelations($model);

        // HasMany ilişkileri
        $this->invalidateHasManyRelations($model);

        // BelongsToMany ilişkileri
        $this->invalidateBelongsToManyRelations($model);
    }

    /**
     * BelongsTo ilişkilerinin cache'lerini temizle
     */
    protected function invalidateBelongsToRelations(Model $model): void
    {
        $reflection = new \ReflectionClass($model);
        $methods = $reflection->getMethods(\ReflectionMethod::IS_PUBLIC);

        foreach ($methods as $method) {
            if ($method->getNumberOfParameters() === 0) {
                try {
                    $relation = $model->{$method->getName()}();

                    if ($relation instanceof \Illuminate\Database\Eloquent\Relations\BelongsTo) {
                        $related = $relation->getRelated();
                        if (method_exists($related, 'flushCache')) {
                            $related->flushCache();
                        }
                    }
                } catch (\Exception $e) {
                    // Skip invalid relations
                }
            }
        }
    }

    /**
     * HasMany ilişkilerinin cache'lerini temizle
     */
    protected function invalidateHasManyRelations(Model $model): void
    {
        $reflection = new \ReflectionClass($model);
        $methods = $reflection->getMethods(\ReflectionMethod::IS_PUBLIC);

        foreach ($methods as $method) {
            if ($method->getNumberOfParameters() === 0) {
                try {
                    $relation = $model->{$method->getName()}();

                    if ($relation instanceof \Illuminate\Database\Eloquent\Relations\HasMany ||
                        $relation instanceof \Illuminate\Database\Eloquent\Relations\HasOne) {
                        $related = $relation->getRelated();
                        if (method_exists($related, 'flushCache')) {
                            $related->flushCache();
                        }
                    }
                } catch (\Exception $e) {
                    // Skip invalid relations
                }
            }
        }
    }

    /**
     * BelongsToMany ilişkilerinin cache'lerini temizle
     */
    protected function invalidateBelongsToManyRelations(Model $model): void
    {
        $reflection = new \ReflectionClass($model);
        $methods = $reflection->getMethods(\ReflectionMethod::IS_PUBLIC);

        foreach ($methods as $method) {
            if ($method->getNumberOfParameters() === 0) {
                try {
                    $relation = $model->{$method->getName()}();

                    if ($relation instanceof \Illuminate\Database\Eloquent\Relations\BelongsToMany) {
                        $related = $relation->getRelated();
                        if (method_exists($related, 'flushCache')) {
                            $related->flushCache();
                        }
                    }
                } catch (\Exception $e) {
                    // Skip invalid relations
                }
            }
        }
    }

    /**
     * Belirli attribute'lara göre cache'leri temizle
     */
    protected function invalidateByAttributes(Model $model, array $attributes): void
    {
        // Model'de attribute-based cache invalidation varsa kullan
        if (method_exists($model, 'getCacheKeysForAttributes')) {
            $keys = $model->getCacheKeysForAttributes($attributes);
            foreach ($keys as $key) {
                $this->cache->forget($key);
            }
        }
    }

    /**
     * Pattern'e göre cache'leri temizle (tag desteklenmeyen cache'ler için)
     *
     * Redis keys() yerine SCAN kullanarak production-safe pattern matching yapar.
     */
    protected function invalidateByPattern(Model $model): void
    {
        if (!method_exists($model, 'getCachePrefix')) {
            return;
        }

        $prefix = $model->getCachePrefix();
        $modelClass = get_class($model);
        $pattern = $prefix . '*' . str_replace('\\', '_', $modelClass) . '*';

        // Redis gibi pattern destekleyen cache'ler için
        if (method_exists($this->cache->getStore(), 'getRedis')) {
            $redis = $this->cache->getStore()->getRedis();

            // keys() yerine SCAN kullan (production-safe)
            $keys = $this->scanKeys($redis, $pattern);

            // Batch delete
            if (!empty($keys)) {
                // Redis DEL komutu array kabul eder
                if (method_exists($redis, 'del')) {
                    $redis->del($keys);
                } else {
                    // Fallback: tek tek sil
                    foreach ($keys as $key) {
                        $this->cache->forget($key);
                    }
                }
            }
        }
    }

    /**
     * SCAN kullanarak pattern'e uyan key'leri bul
     *
     * @param mixed $redis
     * @param string $pattern
     * @return array
     */
    protected function scanKeys($redis, string $pattern): array
    {
        $keys = [];
        $cursor = 0;
        $maxIterations = 1000; // Timeout koruması
        $maxKeys = 10000; // Memory koruması
        $iteration = 0;

        do {
            try {
                // SCAN komutu
                if (method_exists($redis, 'scan')) {
                    $result = $redis->scan($cursor, [
                        'MATCH' => $pattern,
                        'COUNT' => 100, // Batch size
                    ]);
                } else {
                    // Fallback: keys() kullan (sadece development için)
                    if (app()->environment('local', 'testing')) {
                        $result = [0, $redis->keys($pattern)];
                    } else {
                        \Log::warning('Redis SCAN not available, skipping pattern invalidation', [
                            'pattern' => $pattern,
                        ]);
                        break;
                    }
                }

                $cursor = is_array($result) ? ($result[0] ?? 0) : 0;
                $foundKeys = is_array($result) ? ($result[1] ?? []) : [];

                $keys = array_merge($keys, $foundKeys);

                $iteration++;

                // Safety checks
                if ($iteration > $maxIterations) {
                    \Log::warning('SCAN iteration limit reached', [
                        'pattern' => $pattern,
                        'keys_found' => count($keys),
                    ]);
                    break;
                }

                if (count($keys) > $maxKeys) {
                    \Log::warning('Too many cache keys to invalidate', [
                        'pattern' => $pattern,
                        'keys_found' => count($keys),
                    ]);
                    break;
                }
            } catch (\Exception $e) {
                \Log::error('Error during cache key scanning', [
                    'pattern' => $pattern,
                    'error' => $e->getMessage(),
                ]);
                break;
            }
        } while ($cursor !== 0 && $cursor !== '0');

        return array_unique($keys);
    }

    /**
     * Belirli bir query için cache'i temizle
     */
    public function invalidateQueryCache(Model $model, array $queryConditions = []): void
    {
        if (!method_exists($model, 'makeCacheKey')) {
            return;
        }

        try {
            $builder = $model->newQuery();

            foreach ($queryConditions as $column => $value) {
                $builder->where($column, $value);
            }

            // Query'ye göre cache key oluştur ve temizle
            $key = $model->makeCacheKey(['*']);
            $this->cache->forget($key);
        } catch (\Exception $e) {
            // Silently fail
        }
    }
}
