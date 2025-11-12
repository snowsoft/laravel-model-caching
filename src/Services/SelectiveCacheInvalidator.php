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
        } catch (\Exception $e) {
            // Silently fail
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
            $keys = $redis->keys($pattern);
            foreach ($keys as $key) {
                $this->cache->forget($key);
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
