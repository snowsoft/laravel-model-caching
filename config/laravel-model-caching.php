<?php

return [
    'cache-prefix' => '',

    'enabled' => env('MODEL_CACHE_ENABLED', true),

    'use-database-keying' => env('MODEL_CACHE_USE_DATABASE_KEYING', true),

    'store' => env('MODEL_CACHE_STORE'),

    // Multi-tenancy support
    'multi-tenancy' => [
        'enabled' => env('MODEL_CACHE_MULTI_TENANCY_ENABLED', false),

        // Tenant identifier resolver callback
        // Example: fn() => auth()->user()->tenant_id
        // Example: fn() => request()->header('X-Tenant-ID')
        'tenant-resolver' => env('MODEL_CACHE_TENANT_RESOLVER', null),

        // Use separate cache stores per tenant
        'use-tenant-store' => env('MODEL_CACHE_USE_TENANT_STORE', false),

        // Cache store naming pattern for tenants
        // {tenant_id} will be replaced with actual tenant identifier
        'tenant-store-pattern' => env('MODEL_CACHE_TENANT_STORE_PATTERN', 'tenant_{tenant_id}'),
    ],

    // Multiple database connections cache store mapping
    'database-stores' => [
        // Example:
        // 'mysql' => 'redis',
        // 'pgsql' => 'memcached',
        // 'tenant_db_1' => 'redis_tenant_1',
    ],

    // Per-connection cache store configuration
    'connection-stores' => env('MODEL_CACHE_CONNECTION_STORES', null)
        ? json_decode(env('MODEL_CACHE_CONNECTION_STORES'), true)
        : [],

    // Selective cache invalidation (sadece ilgili cache'leri temizle)
    'use-selective-invalidation' => env('MODEL_CACHE_USE_SELECTIVE_INVALIDATION', true),

    // Background cache refresh
    'background-refresh' => [
        'enabled' => env('MODEL_CACHE_BACKGROUND_REFRESH_ENABLED', false),
        'queue' => env('MODEL_CACHE_REFRESH_QUEUE', 'default'),
        'delay' => env('MODEL_CACHE_REFRESH_DELAY', 0), // seconds
    ],

    // Search index (for development/test environments)
    // Arama ve indexleme için ara veritabanı desteği
    'search-index' => [
        'enabled' => env('MODEL_CACHE_SEARCH_INDEX_ENABLED', false),
        'driver' => env('MODEL_CACHE_SEARCH_INDEX_DRIVER', null), // 'mongodb' or 'pgsql'
        'connection' => env('MODEL_CACHE_SEARCH_INDEX_CONNECTION', null), // Connection name
    ],
];
