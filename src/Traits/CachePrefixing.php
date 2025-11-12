<?php namespace Snowsoft\LaravelModelCaching\Traits;

use Snowsoft\LaravelModelCaching\TenantResolver;
use Illuminate\Container\Container;

trait CachePrefixing
{
    /**
     * Static cache for prefix calculation
     * Key: config hash, Value: prefix string
     */
    protected static $prefixCache = [];
    protected static $configHash = null;

    protected function getCachePrefix() : string
    {
        $config = Container::getInstance()->make("config");

        // Calculate config hash for caching
        $currentHash = $this->calculateConfigHash($config);

        // Config değişmediyse cache'den al
        if (self::$configHash === $currentHash && isset(self::$prefixCache[$currentHash])) {
            $basePrefix = self::$prefixCache[$currentHash];

            // Model-specific prefix ekle (cache'lenemez çünkü model'e özel)
            if ($this->model && property_exists($this->model, "cachePrefix")) {
                return $basePrefix . $this->model->cachePrefix . ":";
            }

            return $basePrefix;
        }

        // Yeni prefix hesapla
        $cachePrefix = "snowsoft:laravel-model-caching:";

        // Add tenant identifier if multi-tenancy is enabled
        if (TenantResolver::isEnabled()) {
            $tenantId = TenantResolver::getTenantId();
            if ($tenantId) {
                $cachePrefix .= "tenant:{$tenantId}:";
            }
        }

        $useDatabaseKeying = $config->get("laravel-model-caching.use-database-keying");

        if ($useDatabaseKeying) {
            $cachePrefix .= $this->getConnectionName() . ":";
            $cachePrefix .= $this->getDatabaseName() . ":";
        }

        $configPrefix = $config->get("laravel-model-caching.cache-prefix", "");

        if ($configPrefix) {
            $cachePrefix .= $configPrefix . ":";
        }

        // Cache'le (model-specific prefix hariç)
        self::$configHash = $currentHash;
        self::$prefixCache[$currentHash] = $cachePrefix;

        // Model-specific prefix ekle
        if ($this->model && property_exists($this->model, "cachePrefix")) {
            $cachePrefix .= $this->model->cachePrefix . ":";
        }

        return $cachePrefix;
    }

    /**
     * Calculate hash of config values that affect cache prefix
     *
     * @param \Illuminate\Contracts\Config\Repository $config
     * @return string
     */
    protected function calculateConfigHash($config): string
    {
        return md5(serialize([
            $config->get("laravel-model-caching.cache-prefix", ""),
            $config->get("laravel-model-caching.use-database-keying"),
            TenantResolver::isEnabled() ? TenantResolver::getTenantId() : null,
            $this->getConnectionName(),
            $this->getDatabaseName(),
        ]));
    }

    protected function getDatabaseName() : string
    {
        return $this->model->getConnection()->getDatabaseName();
    }

    protected function getConnectionName() : string
    {
        return $this->model->getConnection()->getName();
    }
}
