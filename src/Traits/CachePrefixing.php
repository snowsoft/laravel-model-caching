<?php namespace Snowsoft\LaravelModelCaching\Traits;

use Snowsoft\LaravelModelCaching\TenantResolver;
use Illuminate\Container\Container;

trait CachePrefixing
{
    protected function getCachePrefix() : string
    {
        $cachePrefix = "snowsoft:laravel-model-caching:";

        // Add tenant identifier if multi-tenancy is enabled
        if (TenantResolver::isEnabled()) {
            $tenantId = TenantResolver::getTenantId();
            if ($tenantId) {
                $cachePrefix .= "tenant:{$tenantId}:";
            }
        }

        $useDatabaseKeying = Container::getInstance()
            ->make("config")
            ->get("laravel-model-caching.use-database-keying");

        if ($useDatabaseKeying) {
            $cachePrefix .= $this->getConnectionName() . ":";
            $cachePrefix .= $this->getDatabaseName() . ":";
        }

        $configPrefix = Container::getInstance()
            ->make("config")
            ->get("laravel-model-caching.cache-prefix", "");

        if ($configPrefix) {
            $cachePrefix .= $configPrefix . ":";
        }

        if ($this->model
            && property_exists($this->model, "cachePrefix")
        ) {
            $cachePrefix .= $this->model->cachePrefix . ":";
        }

        return $cachePrefix;
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
