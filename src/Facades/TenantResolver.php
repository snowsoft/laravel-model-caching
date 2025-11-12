<?php

namespace Snowsoft\LaravelModelCaching\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static string|null getTenantId()
 * @method static bool isEnabled()
 * @method static bool useTenantStore()
 * @method static string|null getTenantStoreName(?string $tenantId = null)
 * @method static void setResolver(\Closure $resolver)
 *
 * @see \Snowsoft\LaravelModelCaching\TenantResolver
 */
class TenantResolver extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'model-cache.tenant-resolver';
    }
}
