<?php

namespace Snowsoft\LaravelModelCaching\Services;

use Illuminate\Container\Container;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Queue;

/**
 * Cache Refresh Service
 *
 * Arka planda cache'leri güncel tutmak için kullanılır.
 * Queue job'ları ile çalışabilir.
 */
class CacheRefreshService
{
    protected $cache;
    protected $config;

    public function __construct()
    {
        $this->cache = Container::getInstance()->make('cache');
        $this->config = Container::getInstance()->make('config');
    }

    /**
     * Model için cache'i yenile
     */
    public function refreshModelCache(Model $model, array $queries = []): void
    {
        if (!method_exists($model, 'isCachable') || !$model->isCachable()) {
            return;
        }

        // Varsayılan query'ler
        if (empty($queries)) {
            $queries = [
                ['method' => 'all'],
                ['method' => 'where', 'column' => 'id', 'value' => $model->id],
            ];
        }

        foreach ($queries as $query) {
            $this->refreshQueryCache($model, $query);
        }
    }

    /**
     * Belirli bir query için cache'i yenile
     */
    public function refreshQueryCache(Model $model, array $queryConfig): void
    {
        try {
            $builder = $model->newQuery();
            $method = $queryConfig['method'] ?? 'get';

            // Query builder'ı oluştur
            if (isset($queryConfig['where'])) {
                foreach ($queryConfig['where'] as $condition) {
                    $builder->where($condition['column'], $condition['operator'] ?? '=', $condition['value']);
                }
            }

            if (isset($queryConfig['column'])) {
                $builder->where($queryConfig['column'], $queryConfig['value']);
            }

            // Cache key oluştur
            if (method_exists($model, 'makeCacheKey')) {
                $columns = $queryConfig['columns'] ?? ['*'];
                $key = $model->makeCacheKey($columns);

                // Cache'i yeniden oluştur
                $tags = method_exists($model, 'makeCacheTags') ? $model->makeCacheTags() : [];
                $cache = $model->cache($tags);

                $cache->rememberForever($key, function () use ($builder, $method) {
                    return $builder->{$method}();
                });
            }
        } catch (\Exception $e) {
            // Log error but don't fail
            if (function_exists('logger')) {
                logger()->error('Cache refresh failed: ' . $e->getMessage());
            }
        }
    }

    /**
     * Queue'ya cache refresh job'ı ekle
     */
    public function queueRefresh(Model $model, array $queries = [], string $queue = 'default'): void
    {
        if (!class_exists('Illuminate\Support\Facades\Queue')) {
            return;
        }

        $job = new \Snowsoft\LaravelModelCaching\Jobs\RefreshModelCacheJob(
            get_class($model),
            $model->getKey(),
            $queries
        );

        Queue::push($job->onQueue($queue));
    }

    /**
     * Belirli bir model için tüm ilgili cache'leri yenile
     */
    public function refreshRelatedCaches(Model $model): void
    {
        $reflection = new \ReflectionClass($model);
        $methods = $reflection->getMethods(\ReflectionMethod::IS_PUBLIC);

        foreach ($methods as $method) {
            if ($method->getNumberOfParameters() === 0 &&
                strpos($method->getName(), 'get') === 0) {
                try {
                    $relation = $model->{$method->getName()}();

                    if ($relation instanceof \Illuminate\Database\Eloquent\Relations\Relation) {
                        $related = $relation->getRelated();
                        if (method_exists($related, 'isCachable') && $related->isCachable()) {
                            $this->refreshModelCache($related);
                        }
                    }
                } catch (\Exception $e) {
                    // Skip invalid relations
                }
            }
        }
    }

    /**
     * Belirli bir zaman aralığında cache'leri yenile
     */
    public function scheduleRefresh(string $modelClass, array $queries = [], int $delay = 0): void
    {
        if (!class_exists('Illuminate\Support\Facades\Queue')) {
            return;
        }

        $job = new \Snowsoft\LaravelModelCaching\Jobs\RefreshModelCacheJob(
            $modelClass,
            null,
            $queries
        );

        if ($delay > 0) {
            Queue::later(now()->addSeconds($delay), $job);
        } else {
            Queue::push($job);
        }
    }

    /**
     * Batch olarak cache'leri yenile
     */
    public function batchRefresh(array $models, array $queries = []): void
    {
        foreach ($models as $model) {
            if ($model instanceof Model) {
                $this->refreshModelCache($model, $queries);
            }
        }
    }
}
