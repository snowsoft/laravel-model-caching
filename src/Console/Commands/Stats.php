<?php

namespace Snowsoft\LaravelModelCaching\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Container\Container;

/**
 * Cache Statistics Command
 *
 * Display cache statistics and performance metrics
 */
class Stats extends Command
{
    protected $signature = 'modelCache:stats
                            {--model= : Show stats for specific model}
                            {--tenant= : Show stats for specific tenant}
                            {--detailed : Show detailed statistics}';

    protected $description = 'Display cache statistics and performance metrics';

    public function handle()
    {
        $model = $this->option('model');
        $tenant = $this->option('tenant');
        $detailed = $this->option('detailed');

        $this->info('📊 Model Cache Statistics');
        $this->newLine();

        if ($model) {
            return $this->showModelStats($model, $detailed);
        }

        if ($tenant) {
            return $this->showTenantStats($tenant, $detailed);
        }

        return $this->showGeneralStats($detailed);
    }

    protected function showGeneralStats(bool $detailed): int
    {
        $config = Container::getInstance()->make('config');
        $store = $config->get('laravel-model-caching.store');
        $cache = Cache::store($store);

        $this->info('General Statistics:');
        $this->table(
            ['Metric', 'Value'],
            [
                ['Cache Store', $store ?? 'default'],
                ['Cache Enabled', $config->get('laravel-model-caching.enabled') ? '✅ Yes' : '❌ No'],
                ['Selective Invalidation', $config->get('laravel-model-caching.use-selective-invalidation') ? '✅ Yes' : '❌ No'],
                ['Multi-Tenancy', $config->get('laravel-model-caching.multi-tenancy.enabled') ? '✅ Yes' : '❌ No'],
            ]
        );

        if ($detailed) {
            $this->newLine();
            $this->info('Cache Store Information:');

            try {
                $storeInstance = $cache->getStore();
                $this->table(
                    ['Property', 'Value'],
                    [
                        ['Store Type', get_class($storeInstance)],
                        ['Supports Tags', method_exists($storeInstance, 'tags') ? '✅ Yes' : '❌ No'],
                    ]
                );
            } catch (\Exception $e) {
                $this->warn('Could not retrieve cache store information: ' . $e->getMessage());
            }
        }

        return 0;
    }

    protected function showModelStats(string $model, bool $detailed): int
    {
        if (!class_exists($model)) {
            $this->error("Model '{$model}' not found.");
            return 1;
        }

        $instance = new $model;

        if (!method_exists($instance, 'makeCacheTags')) {
            $this->error("Model '{$model}' does not use Cachable trait.");
            return 1;
        }

        $this->info("Statistics for model: {$model}");
        $this->newLine();

        // Try to get cache statistics
        try {
            $tags = $instance->makeCacheTags();
            $cache = $instance->cache($tags);

            $this->table(
                ['Metric', 'Value'],
                [
                    ['Model Class', $model],
                    ['Cache Tags', implode(', ', $tags)],
                    ['Cache Enabled', '✅ Yes'],
                ]
            );

            if ($detailed) {
                $this->newLine();
                $this->info('Cache Key Information:');

                // Generate sample cache key
                $sampleKey = $instance->newQuery()->makeCacheKey(['*']);
                $this->line("Sample Cache Key: {$sampleKey}");
                $this->line("Key Length: " . strlen($sampleKey) . " characters");
            }
        } catch (\Exception $e) {
            $this->warn('Could not retrieve model cache statistics: ' . $e->getMessage());
        }

        return 0;
    }

    protected function showTenantStats(string $tenant, bool $detailed): int
    {
        $config = Container::getInstance()->make('config');

        if (!$config->get('laravel-model-caching.multi-tenancy.enabled')) {
            $this->error('Multi-tenancy is not enabled.');
            return 1;
        }

        $this->info("Statistics for tenant: {$tenant}");
        $this->newLine();

        $this->table(
            ['Metric', 'Value'],
            [
                ['Tenant ID', $tenant],
                ['Multi-Tenancy Enabled', '✅ Yes'],
                ['Tenant Store', $config->get('laravel-model-caching.multi-tenancy.use-tenant-store') ? '✅ Yes' : '❌ No'],
            ]
        );

        if ($detailed) {
            $this->newLine();
            $this->info('Tenant Cache Configuration:');

            $storePattern = $config->get('laravel-model-caching.multi-tenancy.tenant-store-pattern', 'tenant_{tenant_id}');
            $storeName = str_replace('{tenant_id}', $tenant, $storePattern);

            $this->line("Store Pattern: {$storePattern}");
            $this->line("Store Name: {$storeName}");
        }

        return 0;
    }
}
