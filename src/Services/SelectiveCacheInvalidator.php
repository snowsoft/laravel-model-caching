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

    /**
     * Static cache for relation detection results
     * Key: model class, Value: array of relation types
     */
    protected static $relationCache = [];

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

            // Fire cache invalidated event
            if (class_exists(\Snowsoft\LaravelModelCaching\Events\CacheInvalidated::class)) {
                event(new \Snowsoft\LaravelModelCaching\Events\CacheInvalidated($model, $tags, 'model_updated'));
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
     * Sadece cached relation'ları invalidate eder (lazy evaluation)
     */
    protected function invalidateRelatedCaches(Model $model): void
    {
        // Model'de cachedRelations property'si varsa sadece onları kullan
        if (property_exists($model, 'cachedRelations') && is_array($model->cachedRelations)) {
            $this->invalidateCachedRelations($model, $model->cachedRelations);
            return;
        }

        // Varsayılan: tüm relation tiplerini kontrol et
        $this->invalidateBelongsToRelations($model);
        $this->invalidateHasManyRelations($model);
        $this->invalidateBelongsToManyRelations($model);
    }

    /**
     * Sadece cached relation'ları invalidate et
     *
     * @param Model $model
     * @param array $relationNames
     * @return void
     */
    protected function invalidateCachedRelations(Model $model, array $relationNames): void
    {
        foreach ($relationNames as $relationName) {
            try {
                $relation = $model->{$relationName}();
                $related = $relation->getRelated();

                if (method_exists($related, 'flushCache')) {
                    $related->flushCache();
                }
            } catch (\Exception $e) {
                \Log::debug('Failed to invalidate cached relation', [
                    'model' => get_class($model),
                    'relation' => $relationName,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    /**
     * BelongsTo ilişkilerinin cache'lerini temizle
     */
    protected function invalidateBelongsToRelations(Model $model): void
    {
        $modelClass = get_class($model);
        $cacheKey = $modelClass . ':belongsTo';

        // Cache'den al
        if (!isset(self::$relationCache[$cacheKey])) {
            self::$relationCache[$cacheKey] = $this->detectRelations(
                $model,
                \Illuminate\Database\Eloquent\Relations\BelongsTo::class
            );
        }

        foreach (self::$relationCache[$cacheKey] as $relationName) {
            try {
                $relation = $model->{$relationName}();
                $related = $relation->getRelated();

                if (method_exists($related, 'flushCache')) {
                    $related->flushCache();
                }
            } catch (\Exception $e) {
                \Log::debug('Failed to invalidate belongsTo relation cache', [
                    'model' => $modelClass,
                    'relation' => $relationName,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    /**
     * HasMany ilişkilerinin cache'lerini temizle
     */
    protected function invalidateHasManyRelations(Model $model): void
    {
        $modelClass = get_class($model);
        $cacheKey = $modelClass . ':hasMany';

        // Cache'den al
        if (!isset(self::$relationCache[$cacheKey])) {
            self::$relationCache[$cacheKey] = $this->detectRelations(
                $model,
                [
                    \Illuminate\Database\Eloquent\Relations\HasMany::class,
                    \Illuminate\Database\Eloquent\Relations\HasOne::class,
                ]
            );
        }

        foreach (self::$relationCache[$cacheKey] as $relationName) {
            try {
                $relation = $model->{$relationName}();
                $related = $relation->getRelated();

                if (method_exists($related, 'flushCache')) {
                    $related->flushCache();
                }
            } catch (\Exception $e) {
                \Log::debug('Failed to invalidate hasMany relation cache', [
                    'model' => $modelClass,
                    'relation' => $relationName,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    /**
     * BelongsToMany ilişkilerinin cache'lerini temizle
     */
    protected function invalidateBelongsToManyRelations(Model $model): void
    {
        $modelClass = get_class($model);
        $cacheKey = $modelClass . ':belongsToMany';

        // Cache'den al
        if (!isset(self::$relationCache[$cacheKey])) {
            self::$relationCache[$cacheKey] = $this->detectRelations(
                $model,
                \Illuminate\Database\Eloquent\Relations\BelongsToMany::class
            );
        }

        foreach (self::$relationCache[$cacheKey] as $relationName) {
            try {
                $relation = $model->{$relationName}();
                $related = $relation->getRelated();

                if (method_exists($related, 'flushCache')) {
                    $related->flushCache();
                }
            } catch (\Exception $e) {
                \Log::debug('Failed to invalidate belongsToMany relation cache', [
                    'model' => $modelClass,
                    'relation' => $relationName,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    /**
     * Detect relations of a specific type for a model
     *
     * @param Model $model
     * @param string|array $relationTypes
     * @return array
     */
    protected function detectRelations(Model $model, $relationTypes): array
    {
        $relations = [];
        $relationTypes = is_array($relationTypes) ? $relationTypes : [$relationTypes];

        // Model'de cachedRelations property'si varsa kullan
        if (property_exists($model, 'cachedRelations')) {
            $cachedRelations = $model->cachedRelations;
            if (is_array($cachedRelations)) {
                foreach ($cachedRelations as $relationName) {
                    try {
                        $relation = $model->{$relationName}();
                        foreach ($relationTypes as $type) {
                            if ($relation instanceof $type) {
                                $relations[] = $relationName;
                                break;
                            }
                        }
                    } catch (\Exception $e) {
                        // Skip invalid relations
                    }
                }
                return $relations;
            }
        }

        // Reflection kullanarak tespit et (sadece ilk seferinde)
        $reflection = new \ReflectionClass($model);
        $methods = $reflection->getMethods(\ReflectionMethod::IS_PUBLIC);

        foreach ($methods as $method) {
            if ($method->getNumberOfParameters() === 0) {
                try {
                    $relation = $model->{$method->getName()}();

                    foreach ($relationTypes as $type) {
                        if ($relation instanceof $type) {
                            $relations[] = $method->getName();
                            break;
                        }
                    }
                } catch (\Exception $e) {
                    // Skip invalid relations
                }
            }
        }

        return $relations;
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

            // Batch delete with pipeline for better performance
            if (!empty($keys)) {
                $this->batchDeleteKeys($redis, $keys);
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
     * Batch delete keys using Redis pipeline for better performance
     *
     * @param mixed $redis
     * @param array $keys
     * @return void
     */
    protected function batchDeleteKeys($redis, array $keys): void
    {
        try {
            // Redis DEL komutu array kabul eder - en hızlı yöntem
            if (method_exists($redis, 'del')) {
                // Chunk keys if too many (Redis has limits)
                $chunks = array_chunk($keys, 1000);
                foreach ($chunks as $chunk) {
                    $redis->del($chunk);
                }
            } elseif (method_exists($redis, 'pipeline')) {
                // Pipeline kullan (daha yavaş ama batch işlem)
                $pipeline = $redis->pipeline();
                foreach ($keys as $key) {
                    $pipeline->del($key);
                }
                $pipeline->execute();
            } else {
                // Fallback: tek tek sil
                foreach ($keys as $key) {
                    $this->cache->forget($key);
                }
            }
        } catch (\Exception $e) {
            \Log::warning('Batch delete failed, falling back to individual deletes', [
                'error' => $e->getMessage(),
                'keys_count' => count($keys),
            ]);

            // Fallback: tek tek sil
            foreach ($keys as $key) {
                try {
                    $this->cache->forget($key);
                } catch (\Exception $deleteException) {
                    // Skip individual failures
                }
            }
        }
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
