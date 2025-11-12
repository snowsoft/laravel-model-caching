<?php

namespace Snowsoft\LaravelModelCaching\Traits;

use Illuminate\Support\Facades\DB;

/**
 * Bulk Operations Trait
 *
 * Toplu güncelleme ve silme işlemleri için cache desteği
 */
trait BulkOperations
{
    /**
     * Bulk update with cache invalidation
     *
     * @param array $values
     * @param array $conditions
     * @return int
     */
    public function bulkUpdate(array $values, array $conditions = []): int
    {
        $query = $this->newQuery();

        foreach ($conditions as $column => $value) {
            if (is_array($value)) {
                $query->whereIn($column, $value);
            } else {
                $query->where($column, $value);
            }
        }

        // Get affected models before update
        $affectedModels = $query->get();

        // Perform update
        $updated = $query->update($values);

        // Invalidate cache for affected models
        if ($updated > 0) {
            $this->invalidateBulkCache($affectedModels);
        }

        return $updated;
    }

    /**
     * Bulk delete with cache invalidation
     *
     * @param array $conditions
     * @return int
     */
    public function bulkDelete(array $conditions = []): int
    {
        $query = $this->newQuery();

        foreach ($conditions as $column => $value) {
            if (is_array($value)) {
                $query->whereIn($column, $value);
            } else {
                $query->where($column, $value);
            }
        }

        // Get affected models before delete
        $affectedModels = $query->get();

        // Perform delete
        $deleted = $query->delete();

        // Invalidate cache for affected models
        if ($deleted > 0) {
            $this->invalidateBulkCache($affectedModels);
        }

        return $deleted;
    }

    /**
     * Bulk insert with cache management
     *
     * @param array $values
     * @return bool
     */
    public function bulkInsert(array $values): bool
    {
        $inserted = DB::table($this->getTable())->insert($values);

        if ($inserted) {
            // Invalidate model cache
            if (method_exists($this, 'flushCache')) {
                $this->flushCache();
            }
        }

        return $inserted;
    }

    /**
     * Update or insert with cache invalidation
     *
     * @param array $attributes
     * @param array $values
     * @return \Illuminate\Database\Eloquent\Model|static
     */
    public function updateOrInsert(array $attributes, array $values = [])
    {
        $model = $this->where($attributes)->first();

        if ($model) {
            $model->update($values);
            return $model;
        }

        return $this->create(array_merge($attributes, $values));
    }

    /**
     * Upsert (update or insert multiple) with cache invalidation
     *
     * @param array $values
     * @param array|string $uniqueBy
     * @param array|null $update
     * @return int
     */
    public function upsert(array $values, $uniqueBy, array $update = null): int
    {
        $result = parent::upsert($values, $uniqueBy, $update);

        if ($result > 0) {
            // Invalidate model cache
            if (method_exists($this, 'flushCache')) {
                $this->flushCache();
            }
        }

        return $result;
    }

    /**
     * Invalidate cache for bulk operations
     *
     * @param \Illuminate\Database\Eloquent\Collection $models
     * @return void
     */
    protected function invalidateBulkCache($models): void
    {
        if (method_exists($this, 'flushCache')) {
            // Flush entire model cache for bulk operations
            $this->flushCache();
        }

        // Also invalidate individual model caches if needed
        foreach ($models as $model) {
            if (method_exists($model, 'flushCache')) {
                $model->flushCache();
            }
        }
    }

    /**
     * Transaction-aware bulk update
     *
     * @param array $values
     * @param array $conditions
     * @return int
     */
    public function transactionBulkUpdate(array $values, array $conditions = []): int
    {
        return DB::transaction(function () use ($values, $conditions) {
            $updated = $this->bulkUpdate($values, $conditions);

            // Cache invalidation will happen after transaction commits
            DB::afterCommit(function () use ($updated) {
                if ($updated > 0 && method_exists($this, 'flushCache')) {
                    $this->flushCache();
                }
            });

            return $updated;
        });
    }

    /**
     * Transaction-aware bulk delete
     *
     * @param array $conditions
     * @return int
     */
    public function transactionBulkDelete(array $conditions = []): int
    {
        return DB::transaction(function () use ($conditions) {
            $deleted = $this->bulkDelete($conditions);

            // Cache invalidation will happen after transaction commits
            DB::afterCommit(function () use ($deleted) {
                if ($deleted > 0 && method_exists($this, 'flushCache')) {
                    $this->flushCache();
                }
            });

            return $deleted;
        });
    }
}
