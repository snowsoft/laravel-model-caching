<?php

namespace Snowsoft\LaravelModelCaching\Traits;

use Closure;
use Snowsoft\LaravelModelCaching\CachedBuilder;
use Snowsoft\LaravelModelCaching\CacheKey;
use Snowsoft\LaravelModelCaching\CacheTags;
use Illuminate\Cache\TaggableStore;
use Illuminate\Container\Container;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Carbon;

trait Caching
{
    protected $isCachable = true;
    protected $scopesAreApplied = false;
    protected $macroKey = "";
    protected $withoutGlobalScopes = [];
    protected $withoutAllGlobalScopes = false;

    public function __call($method, $parameters)
    {
        $result = parent::__call($method, $parameters);

        if (isset($this->localMacros[$method])) {
            $this->macroKey .= "-{$method}";

            if ($parameters) {
                $this->macroKey .= implode("_", $parameters);
            }
        }

        return $result;
    }

    public function applyScopes()
    {
        if ($this->scopesAreApplied) {
            return $this;
        }

        return parent::applyScopes();
    }

    protected function applyScopesToInstance()
    {
        if (
            ! property_exists($this, "scopes")
            || $this->scopesAreApplied
            || $this->withoutAllGlobalScopes
        ) {
            return;
        }

        foreach ($this->scopes as $identifier => $scope) {
            if (
                ! isset($this->scopes[$identifier])
                || isset($this->withoutGlobalScopes[$identifier])
            ) {
                continue;
            }

            $this->callScope(function () use ($scope) {
                if ($scope instanceof Closure) {
                    $scope($this);
                }

                if (
                    $scope instanceof Scope
                    && $this instanceof CachedBuilder
                ) {
                    $scope->apply($this, $this->getModel());
                }
            });
        }

        $this->scopesAreApplied = true;
    }

    public function cache(array $tags = [])
    {
        $cache = Container::getInstance()
            ->make("cache");

        // Determine which cache store to use
        $storeName = $this->resolveCacheStore();

        if ($storeName) {
            $cache = $cache->store($storeName);
        }

        if (is_subclass_of($cache->getStore(), TaggableStore::class)) {
            $cache = $cache->tags($tags);
        }

        return $cache;
    }

    /**
     * Resolve the appropriate cache store based on tenant, connection, and config
     */
    protected function resolveCacheStore(): ?string
    {
        $config = Container::getInstance()->make("config");

        // Priority 1: Tenant-specific cache store (if enabled)
        if (class_exists(\Snowsoft\LaravelModelCaching\TenantResolver::class)) {
            $tenantStore = \Snowsoft\LaravelModelCaching\TenantResolver::getTenantStoreName();
            if ($tenantStore) {
                return $tenantStore;
            }
        }

        // Priority 2: Connection-specific cache store
        $connectionStore = $this->getConnectionCacheStore();
        if ($connectionStore) {
            return $connectionStore;
        }

        // Priority 3: Global cache store from config
        $globalStore = $config->get("laravel-model-caching.store");
        if ($globalStore) {
            return $globalStore;
        }

        return null;
    }

    /**
     * Get cache store for current database connection
     */
    protected function getConnectionCacheStore(): ?string
    {
        if (!property_exists($this, 'model') || !$this->model) {
            return null;
        }

        try {
            $connectionName = $this->model->getConnection()->getName();
            $config = Container::getInstance()->make("config");

            // Check connection-stores config
            $connectionStores = $config->get("laravel-model-caching.connection-stores", []);
            if (isset($connectionStores[$connectionName])) {
                return $connectionStores[$connectionName];
            }

            // Check database-stores config (legacy support)
            $databaseStores = $config->get("laravel-model-caching.database-stores", []);
            if (isset($databaseStores[$connectionName])) {
                return $databaseStores[$connectionName];
            }
        } catch (\Exception $e) {
            // Silently fail
        }

        return null;
    }

    public function disableModelCaching()
    {
        $this->isCachable = false;

        return $this;
    }

    public function flushCache(array $tags = [])
    {
        if (count($tags) === 0) {
            $tags = $this->makeCacheTags();
        }

        $this->cache($tags)->flush();

        [$cacheCooldown] = $this->getModelCacheCooldown($this);

        if ($cacheCooldown) {
            $cachePrefix = $this->getCachePrefix();
            $modelClassName = get_class($this);
            $cacheKey = "{$cachePrefix}:{$modelClassName}-cooldown:saved-at";

            $this->cache()
                ->rememberForever($cacheKey, function () {
                    return (new Carbon)->now();
                });
        }
    }

    protected function getCachePrefix() : string
    {
        $cachePrefix = Container::getInstance()
            ->make("config")
            ->get("laravel-model-caching.cache-prefix", "");

        if (
            $this->model
            && property_exists($this->model, "cachePrefix")
        ) {
            $cachePrefix = $this->model->cachePrefix;
        }

        $cachePrefix = $cachePrefix
            ? "{$cachePrefix}:"
            : "";

        return "snowsoft:laravel-model-caching:"
            . $cachePrefix;
    }

    protected function makeCacheKey(
        array $columns = ['*'],
        $idColumn = null,
        string $keyDifferentiator = ''
    ) : string {
        $this->applyScopesToInstance();
        $eagerLoad = $this->eagerLoad ?? [];
        $model = $this;

        if (property_exists($this, "model")) {
            $model = $this->model;
        }

        if (method_exists($this, "getModel")) {
            $model = $this->getModel();
        }

        $query = $this->query
            ?? Container::getInstance()
                ->make("db")
                ->query();

        if (
            $this->query
            && method_exists($this->query, "getQuery")
        ) {
            $query = $this->query->getQuery();
        }

        return (new CacheKey($eagerLoad, $model, $query, $this->macroKey, $this->withoutGlobalScopes, $this->withoutAllGlobalScopes))
            ->make($columns, $idColumn, $keyDifferentiator);
    }

    protected function makeCacheTags() : array
    {
        $eagerLoad = $this->eagerLoad ?? [];
        $model = $this->getModel() instanceof Model
            ? $this->getModel()
            : $this;
        $query = $this->query instanceof Builder
            ? $this->query
            : Container::getInstance()
                ->make("db")
                ->query();
        $tags = (new CacheTags($eagerLoad, $model, $query))
            ->make();

        return $tags;
    }

    public function getModelCacheCooldown(Model $instance) : array
    {
        if (! $instance->cacheCooldownSeconds) {
            return [null, null, null];
        }

        $cachePrefix = $this->getCachePrefix();
        $modelClassName = get_class($instance);
        [$cacheCooldown, $invalidatedAt, $savedAt] = $this
            ->getCacheCooldownDetails($instance, $cachePrefix, $modelClassName);

        if (! $cacheCooldown || $cacheCooldown === 0) {
            return [null, null, null];
        }

        return [$cacheCooldown, $invalidatedAt, $savedAt];
    }

    protected function getCacheCooldownDetails(
        Model $instance,
        string $cachePrefix,
        string $modelClassName
    ) : array {
        return [
            $instance
                ->cache()
                ->get("{$cachePrefix}:{$modelClassName}-cooldown:seconds"),
            $instance
                ->cache()
                ->get("{$cachePrefix}:{$modelClassName}-cooldown:invalidated-at"),
            $instance
                ->cache()
                ->get("{$cachePrefix}:{$modelClassName}-cooldown:saved-at"),
        ];
    }

    protected function checkCooldownAndRemoveIfExpired(Model $instance)
    {
        [$cacheCooldown, $invalidatedAt] = $this->getModelCacheCooldown($instance);

        if (
            ! $cacheCooldown
            || (new Carbon)->now()->diffInSeconds($invalidatedAt, true) < $cacheCooldown
        ) {
            return;
        }

        $cachePrefix = $this->getCachePrefix();
        $modelClassName = get_class($instance);

        $instance
            ->cache()
            ->forget("{$cachePrefix}:{$modelClassName}-cooldown:seconds");
        $instance
            ->cache()
            ->forget("{$cachePrefix}:{$modelClassName}-cooldown:invalidated-at");
        $instance
            ->cache()
            ->forget("{$cachePrefix}:{$modelClassName}-cooldown:saved-at");
        $instance->flushCache();
    }

    protected function checkCooldownAndFlushAfterPersisting(Model $instance, string $relationship = "")
    {
        if (! $this->isCachable()) {
            return;
        }

        [$cacheCooldown, $invalidatedAt] = $instance->getModelCacheCooldown($instance);

        if (! $cacheCooldown) {
            $instance->flushCache();

            if ($relationship) {
                $relationshipInstance = $instance->$relationship()->getModel();

                if (method_exists($relationshipInstance, "flushCache")) {
                    $relationshipInstance->flushCache();
                }
            }

            return;
        }

        $this->setCacheCooldownSavedAtTimestamp($instance);

        if ((new Carbon)->now()->diffInSeconds($invalidatedAt, true) >= $cacheCooldown) {
            $instance->flushCache();

            if ($relationship) {
                $instance->$relationship()->getModel()->flushCache();
            }
        }
    }

    public function isCachable() : bool
    {
        $isCacheDisabled = ! Container::getInstance()
            ->make("config")
            ->get("laravel-model-caching.enabled");

        if ($isCacheDisabled) {
            return false;
        }

        $allRelationshipsAreCachable = true;

        if (
            property_exists($this, "eagerLoad")
            && $this->eagerLoad
        ) {
            $allRelationshipsAreCachable = collect($this
                ->eagerLoad)
                ->keys()
                ->reduce(function ($carry, $related) {
                    if (
                        ! method_exists($this->model, $related)
                        || $carry === false
                    ) {
                        return $carry;
                    }

                    $relatedModel = $this->model->$related()->getRelated();

                    if (
                        ! method_exists($relatedModel, "isCachable")
                        || ! $relatedModel->isCachable()
                    ) {
                        return false;
                    }

                    return true;
                })
                ?? true;
        }

        return $this->isCachable
            && $allRelationshipsAreCachable;
    }

    protected function setCacheCooldownSavedAtTimestamp(Model $instance)
    {
        $cachePrefix = $this->getCachePrefix();
        $modelClassName = get_class($instance);
        $cacheKey = "{$cachePrefix}:{$modelClassName}-cooldown:saved-at";

        $instance->cache()
            ->rememberForever($cacheKey, function () {
                return (new Carbon)->now();
            });
    }
}
