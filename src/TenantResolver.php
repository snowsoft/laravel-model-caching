<?php

namespace Snowsoft\LaravelModelCaching;

use Closure;
use Illuminate\Container\Container;

class TenantResolver
{
    protected static ?Closure $resolver = null;

    /**
     * Set custom tenant resolver
     */
    public static function setResolver(Closure $resolver): void
    {
        self::$resolver = $resolver;
    }

    /**
     * Get current tenant identifier
     */
    public static function getTenantId(): ?string
    {
        $config = Container::getInstance()->make('config');
        $multiTenancyConfig = $config->get('laravel-model-caching.multi-tenancy', []);

        if (!($multiTenancyConfig['enabled'] ?? false)) {
            return null;
        }

        // Use custom resolver if set
        if (self::$resolver !== null) {
            try {
                $tenantId = call_user_func(self::$resolver);
                $tenantId = $tenantId ? (string) $tenantId : null;

                // Validate tenant ID
                if ($tenantId && !self::validateTenantId($tenantId)) {
                    \Log::warning('Invalid tenant ID format', ['tenant_id' => $tenantId]);
                    return null;
                }

                return $tenantId;
            } catch (\Exception $e) {
                \Log::error('Tenant resolver error', ['error' => $e->getMessage()]);
                return null;
            }
        }

        // Try to resolve from config
        $resolverConfig = $multiTenancyConfig['tenant-resolver'] ?? null;
        if ($resolverConfig && is_string($resolverConfig)) {
            try {
                $resolver = eval("return {$resolverConfig};");
                if ($resolver instanceof Closure) {
                    $tenantId = call_user_func($resolver);
                    $tenantId = $tenantId ? (string) $tenantId : null;

                    // Validate tenant ID
                    if ($tenantId && !self::validateTenantId($tenantId)) {
                        \Log::warning('Invalid tenant ID format from config resolver', ['tenant_id' => $tenantId]);
                        return null;
                    }

                    return $tenantId;
                }
            } catch (\Exception $e) {
                \Log::error('Tenant resolver config error', ['error' => $e->getMessage()]);
                // Fall through to default resolvers
            }
        }

        // Default resolvers
        try {
            // Try Laravel Tenancy package
            if (class_exists('Stancl\Tenancy\Tenancy')) {
                $tenancy = Container::getInstance()->make('Stancl\Tenancy\Tenancy');
                if ($tenancy->initialized) {
                    return (string) $tenancy->tenant->id;
                }
            }

            // Try Spatie Laravel Multitenancy
            if (class_exists('Spatie\Multitenancy\Models\Tenant')) {
                $tenant = \Spatie\Multitenancy\Models\Tenant::current();
                if ($tenant) {
                    return (string) $tenant->id;
                }
            }

            // Try auth user tenant
            if (function_exists('auth') && auth()->check()) {
                $user = auth()->user();
                if (isset($user->tenant_id)) {
                    return (string) $user->tenant_id;
                }
                if (method_exists($user, 'getTenantId')) {
                    return (string) $user->getTenantId();
                }
            }

            // Try request header
            if (function_exists('request')) {
                $tenantId = request()->header('X-Tenant-ID');
                if ($tenantId) {
                    return (string) $tenantId;
                }
            }
        } catch (\Exception $e) {
            // Silently fail
        }

        return null;
    }

    /**
     * Validate tenant ID format
     *
     * @param string $tenantId
     * @return bool
     */
    protected static function validateTenantId(string $tenantId): bool
    {
        // Sadece alphanumeric, dash, underscore, dot'a izin ver
        // Length: 1-64 karakter
        if (!preg_match('/^[a-zA-Z0-9_.-]{1,64}$/', $tenantId)) {
            return false;
        }

        // Null bytes kontrolü
        if (strpos($tenantId, "\0") !== false) {
            return false;
        }

        return true;
    }

    /**
     * Check if multi-tenancy is enabled
     */
    public static function isEnabled(): bool
    {
        $config = Container::getInstance()->make('config');
        return (bool) $config->get('laravel-model-caching.multi-tenancy.enabled', false);
    }

    /**
     * Check if tenant-specific cache stores should be used
     */
    public static function useTenantStore(): bool
    {
        $config = Container::getInstance()->make('config');
        return (bool) $config->get('laravel-model-caching.multi-tenancy.use-tenant-store', false);
    }

    /**
     * Get tenant-specific cache store name
     */
    public static function getTenantStoreName(?string $tenantId = null): ?string
    {
        if (!self::useTenantStore()) {
            return null;
        }

        $tenantId = $tenantId ?? self::getTenantId();
        if (!$tenantId) {
            return null;
        }

        $config = Container::getInstance()->make('config');
        $pattern = $config->get('laravel-model-caching.multi-tenancy.tenant-store-pattern', 'tenant_{tenant_id}');

        return str_replace('{tenant_id}', $tenantId, $pattern);
    }
}
