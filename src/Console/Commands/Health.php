<?php

namespace Snowsoft\LaravelModelCaching\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Container\Container;

/**
 * Cache Health Check Command
 *
 * Check cache system health and configuration
 */
class Health extends Command
{
    protected $signature = 'modelCache:health
                            {--fix : Attempt to fix issues}';

    protected $description = 'Check cache system health and configuration';

    public function handle()
    {
        $this->info('🏥 Model Cache Health Check');
        $this->newLine();

        $issues = [];
        $warnings = [];

        // Check 1: Cache enabled
        $config = Container::getInstance()->make('config');
        if (!$config->get('laravel-model-caching.enabled')) {
            $warnings[] = 'Cache is disabled in configuration';
        }

        // Check 2: Cache store availability
        $store = $config->get('laravel-model-caching.store');
        try {
            $cache = Cache::store($store ?? 'default');
            $cache->put('health_check', 'ok', 1);
            $value = $cache->get('health_check');

            if ($value !== 'ok') {
                $issues[] = 'Cache store is not working correctly';
            } else {
                $cache->forget('health_check');
            }
        } catch (\Exception $e) {
            $issues[] = "Cache store error: {$e->getMessage()}";
        }

        // Check 3: Tag support
        try {
            $cache = Cache::store($store ?? 'default');
            $storeInstance = $cache->getStore();

            if (!method_exists($storeInstance, 'tags')) {
                $warnings[] = 'Cache store does not support tags. Selective invalidation may not work optimally.';
            }
        } catch (\Exception $e) {
            $warnings[] = "Could not check tag support: {$e->getMessage()}";
        }

        // Check 4: Multi-tenancy configuration
        if ($config->get('laravel-model-caching.multi-tenancy.enabled')) {
            $resolver = $config->get('laravel-model-caching.multi-tenancy.tenant-resolver');
            if (!$resolver) {
                $warnings[] = 'Multi-tenancy is enabled but no tenant resolver is configured';
            }
        }

        // Check 5: Selective invalidation
        if (!$config->get('laravel-model-caching.use-selective-invalidation')) {
            $warnings[] = 'Selective invalidation is disabled. This may impact performance.';
        }

        // Display results
        if (empty($issues) && empty($warnings)) {
            $this->info('✅ All health checks passed!');
            return 0;
        }

        if (!empty($issues)) {
            $this->error('❌ Issues found:');
            foreach ($issues as $issue) {
                $this->line("  • {$issue}");
            }
            $this->newLine();
        }

        if (!empty($warnings)) {
            $this->warn('⚠️  Warnings:');
            foreach ($warnings as $warning) {
                $this->line("  • {$warning}");
            }
            $this->newLine();
        }

        // Summary
        $this->table(
            ['Status', 'Count'],
            [
                ['✅ Passed', count([])],
                ['❌ Issues', count($issues)],
                ['⚠️  Warnings', count($warnings)],
            ]
        );

        return empty($issues) ? 0 : 1;
    }
}
