<?php

namespace Snowsoft\LaravelModelCaching\Services;

use Illuminate\Container\Container;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Update Cache Service
 *
 * Veri güncelleme işlemleri için cache yönetimi
 */
class UpdateCacheService
{
    protected $cache;
    protected $config;

    public function __construct()
    {
        $this->cache = Container::getInstance()->make('cache');
        $this->config = Container::getInstance()->make('config');
    }

    /**
     * Transaction-aware cache invalidation
     *
     * @param callable $callback
     * @return mixed
     */
    public function transaction(callable $callback)
    {
        $pendingInvalidations = [];

        // Transaction başlat
        return DB::transaction(function () use ($callback, &$pendingInvalidations) {
            // Transaction içindeki cache invalidation'ları topla
            $this->collectInvalidations($pendingInvalidations);

            $result = $callback();

            // Transaction commit olduğunda cache'leri temizle
            DB::afterCommit(function () use ($pendingInvalidations) {
                $this->executeInvalidations($pendingInvalidations);
            });

            return $result;
        });
    }

    /**
     * Optimistic locking ile update
     *
     * @param Model $model
     * @param array $values
     * @param int $expectedVersion
     * @return bool
     */
    public function updateWithLock(Model $model, array $values, int $expectedVersion): bool
    {
        // Version kontrolü
        if ($model->version !== $expectedVersion) {
            throw new \Exception('Optimistic lock failed. Model was modified by another process.');
        }

        // Update yap
        $updated = $model->update(array_merge($values, ['version' => $expectedVersion + 1]));

        if ($updated) {
            // Cache'i temizle
            if (method_exists($model, 'flushCache')) {
                $model->flushCache();
            }
        }

        return $updated;
    }

    /**
     * Batch update with selective cache invalidation
     *
     * @param Model $model
     * @param array $updates Array of [id => values]
     * @return int
     */
    public function batchUpdate(Model $model, array $updates): int
    {
        $updated = 0;
        $affectedIds = [];

        foreach ($updates as $id => $values) {
            $instance = $model->find($id);
            if ($instance) {
                $instance->update($values);
                $affectedIds[] = $id;
                $updated++;
            }
        }

        // Sadece etkilenen modellerin cache'lerini temizle
        if ($updated > 0 && method_exists($model, 'flushCache')) {
            $model->flushCache();
        }

        return $updated;
    }

    /**
     * Update conflict resolution
     *
     * @param Model $model
     * @param array $values
     * @param callable $conflictResolver
     * @return Model
     */
    public function updateWithConflictResolution(Model $model, array $values, callable $conflictResolver)
    {
        $attempts = 0;
        $maxAttempts = 3;

        while ($attempts < $maxAttempts) {
            try {
                $freshModel = $model->fresh();

                // Conflict kontrolü
                if ($freshModel->updated_at > $model->updated_at) {
                    // Conflict var, resolver'ı çağır
                    $values = $conflictResolver($freshModel, $values);
                }

                $freshModel->update($values);

                // Cache'i temizle
                if (method_exists($freshModel, 'flushCache')) {
                    $freshModel->flushCache();
                }

                return $freshModel;
            } catch (\Exception $e) {
                $attempts++;
                if ($attempts >= $maxAttempts) {
                    throw $e;
                }
                usleep(100000); // 100ms bekle
            }
        }

        throw new \Exception('Update failed after maximum attempts');
    }

    /**
     * Collect pending invalidations
     *
     * @param array $pendingInvalidations
     * @return void
     */
    protected function collectInvalidations(array &$pendingInvalidations): void
    {
        // Bu metod transaction içindeki cache invalidation'ları toplar
        // Implementation depends on how you want to track invalidations
    }

    /**
     * Execute pending invalidations
     *
     * @param array $pendingInvalidations
     * @return void
     */
    protected function executeInvalidations(array $pendingInvalidations): void
    {
        foreach ($pendingInvalidations as $invalidation) {
            // Cache invalidation'ları çalıştır
            if (isset($invalidation['model']) && method_exists($invalidation['model'], 'flushCache')) {
                $invalidation['model']->flushCache();
            }
        }
    }

    /**
     * Update with cache refresh
     *
     * @param Model $model
     * @param array $values
     * @param bool $refreshCache
     * @return bool
     */
    public function updateWithRefresh(Model $model, array $values, bool $refreshCache = true): bool
    {
        $updated = $model->update($values);

        if ($updated && $refreshCache) {
            // Cache'i temizle ve yeniden doldur
            if (method_exists($model, 'flushCache')) {
                $model->flushCache();
            }

            // Background'da cache'i yenile
            $refreshService = Container::getInstance()->make(
                \Snowsoft\LaravelModelCaching\Services\CacheRefreshService::class
            );
            $refreshService->refreshModelCache($model);
        }

        return $updated;
    }
}
