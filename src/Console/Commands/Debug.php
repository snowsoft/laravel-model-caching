<?php

namespace Snowsoft\LaravelModelCaching\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Container\Container;

/**
 * Cache Debug Command
 *
 * Debug cache keys, tags, and content
 */
class Debug extends Command
{
    protected $signature = 'modelCache:debug
                            {--key= : Debug specific cache key}
                            {--model= : Debug cache for specific model}
                            {--trace : Show cache invalidation trace}
                            {--query= : Generate cache key for query}';

    protected $description = 'Debug cache keys, tags, and content';

    public function handle()
    {
        $key = $this->option('key');
        $model = $this->option('model');
        $trace = $this->option('trace');
        $query = $this->option('query');

        if ($key) {
            return $this->debugKey($key);
        }

        if ($model) {
            return $this->debugModel($model, $trace);
        }

        if ($query) {
            return $this->debugQuery($query);
        }

        $this->error('Please specify --key, --model, or --query option.');
        $this->line('Use --help for more information.');

        return 1;
    }

    protected function debugKey(string $key): int
    {
        $this->info("🔍 Debugging Cache Key: {$key}");
        $this->newLine();

        $config = Container::getInstance()->make('config');
        $store = $config->get('laravel-model-caching.store');
        $cache = Cache::store($store ?? 'default');

        // Check if key exists
        $exists = $cache->has($key);
        $this->line("Key exists: " . ($exists ? '✅ Yes' : '❌ No'));

        if ($exists) {
            $value = $cache->get($key);
            $this->line("Value type: " . gettype($value));
            $this->line("Value size: " . strlen(serialize($value)) . " bytes");

            if ($this->option('verbose')) {
                $this->newLine();
                $this->info('Value content:');
                $this->line(json_encode($value, JSON_PRETTY_PRINT));
            }
        }

        // Check tags
        try {
            $storeInstance = $cache->getStore();
            if (method_exists($storeInstance, 'tags')) {
                $this->line("Supports tags: ✅ Yes");
            } else {
                $this->line("Supports tags: ❌ No");
            }
        } catch (\Exception $e) {
            $this->warn("Could not check tag support: {$e->getMessage()}");
        }

        return 0;
    }

    protected function debugModel(string $model, bool $trace): int
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

        $this->info("🔍 Debugging Model: {$model}");
        $this->newLine();

        // Cache tags
        try {
            $tags = $instance->makeCacheTags();
            $this->info('Cache Tags:');
            foreach ($tags as $tag) {
                $this->line("  • {$tag}");
            }
        } catch (\Exception $e) {
            $this->warn("Could not get cache tags: {$e->getMessage()}");
        }

        $this->newLine();

        // Sample cache key
        try {
            $query = $instance->newQuery();
            $key = $query->makeCacheKey(['*']);
            $this->info('Sample Cache Key:');
            $this->line("  {$key}");
            $this->line("  Length: " . strlen($key) . " characters");
        } catch (\Exception $e) {
            $this->warn("Could not generate cache key: {$e->getMessage()}");
        }

        // Cache prefix
        try {
            $prefix = $instance->getCachePrefix();
            $this->newLine();
            $this->info('Cache Prefix:');
            $this->line("  {$prefix}");
        } catch (\Exception $e) {
            // Ignore
        }

        if ($trace) {
            $this->newLine();
            $this->info('Cache Invalidation Trace:');
            $this->line("  When this model is updated, the following caches will be invalidated:");
            $this->line("  • Model cache tags: " . implode(', ', $tags ?? []));

            // Check relations
            if (property_exists($instance, 'cachedRelations')) {
                $relations = $instance->cachedRelations;
                if (is_array($relations) && !empty($relations)) {
                    $this->line("  • Related models: " . implode(', ', $relations));
                }
            }
        }

        return 0;
    }

    protected function debugQuery(string $queryString): int
    {
        $this->info("🔍 Generating Cache Key for Query");
        $this->newLine();

        // Parse query string (simple format: Model::where('column', 'value'))
        // This is a simplified version - in production, you might want more sophisticated parsing
        $this->line("Query: {$queryString}");
        $this->newLine();
        $this->warn("Query parsing is simplified. For complex queries, use --model option with actual query builder.");

        return 0;
    }
}
