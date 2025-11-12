<?php

namespace Snowsoft\LaravelModelCaching\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Queue;
use Illuminate\Container\Container;

/**
 * Cache Warming Command
 *
 * Pre-warm cache for models
 */
class Warm extends Command
{
    protected $signature = 'modelCache:warm
                            {--model= : Warm cache for specific model}
                            {--all : Warm cache for all cached models}
                            {--strategy=popular : Warming strategy (popular, recent, all)}
                            {--queue : Queue the warming process}
                            {--limit=100 : Limit number of records}';

    protected $description = 'Pre-warm cache for models';

    public function handle()
    {
        $model = $this->option('model');
        $all = $this->option('all');
        $strategy = $this->option('strategy');
        $queue = $this->option('queue');
        $limit = (int) $this->option('limit');

        if ($all) {
            return $this->warmAll($strategy, $queue, $limit);
        }

        if ($model) {
            return $this->warmModel($model, $strategy, $queue, $limit);
        }

        $this->error('Please specify --model or --all option.');
        return 1;
    }

    protected function warmModel(string $model, string $strategy, bool $queue, int $limit): int
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

        $this->info("🔥 Warming cache for model: {$model}");
        $this->newLine();

        if ($queue) {
            return $this->queueWarmModel($model, $strategy, $limit);
        }

        return $this->executeWarmModel($model, $strategy, $limit);
    }

    protected function executeWarmModel(string $model, string $strategy, int $limit): int
    {
        $bar = $this->output->createProgressBar($limit);
        $bar->start();

        try {
            $instance = new $model;
            $warmed = 0;

            switch ($strategy) {
                case 'popular':
                    // Warm most accessed records (assuming 'views' or 'access_count' column)
                    if (method_exists($instance, 'orderBy')) {
                        $records = $instance::orderBy('views', 'desc')
                            ->orWhereNull('views')
                            ->orderBy('id', 'desc')
                            ->limit($limit)
                            ->get();
                    } else {
                        $records = $instance::limit($limit)->get();
                    }
                    break;

                case 'recent':
                    // Warm recently created/updated records
                    $records = $instance::orderBy('created_at', 'desc')
                        ->limit($limit)
                        ->get();
                    break;

                case 'all':
                default:
                    // Warm all records (be careful with large datasets)
                    $records = $instance::limit($limit)->get();
                    break;
            }

            foreach ($records as $record) {
                // Access the record to trigger cache
                $record->toArray();
                $warmed++;
                $bar->advance();
            }

            $bar->finish();
            $this->newLine(2);
            $this->info("✅ Warmed cache for {$warmed} records.");

            return 0;
        } catch (\Exception $e) {
            $bar->finish();
            $this->newLine(2);
            $this->error("Error warming cache: {$e->getMessage()}");
            return 1;
        }
    }

    protected function queueWarmModel(string $model, string $strategy, int $limit): int
    {
        $refreshService = Container::getInstance()->make(
            \Snowsoft\LaravelModelCaching\Services\CacheRefreshService::class
        );

        $config = Container::getInstance()->make('config');
        $queue = $config->get('laravel-model-caching.background-refresh.queue', 'default');

        $this->info("📤 Queueing cache warming job...");

        // Queue warming job (simplified - in production, create a dedicated job)
        $this->warn("Queue warming is not fully implemented. Use --no-queue for immediate warming.");

        return 0;
    }

    protected function warmAll(string $strategy, bool $queue, int $limit): int
    {
        $this->info("🔥 Warming cache for all cached models");
        $this->newLine();

        // Find all models that use Cachable trait
        $models = $this->findCachedModels();

        if (empty($models)) {
            $this->warn("No cached models found.");
            return 0;
        }

        $this->info("Found " . count($models) . " cached models:");
        foreach ($models as $model) {
            $this->line("  • {$model}");
        }

        $this->newLine();

        if (!$this->confirm('Do you want to warm cache for all these models?', true)) {
            return 0;
        }

        $bar = $this->output->createProgressBar(count($models));
        $bar->start();

        $warmed = 0;
        foreach ($models as $model) {
            try {
                $this->executeWarmModel($model, $strategy, $limit);
                $warmed++;
            } catch (\Exception $e) {
                $this->warn("Failed to warm {$model}: {$e->getMessage()}");
            }
            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);
        $this->info("✅ Warmed cache for {$warmed} models.");

        return 0;
    }

    protected function findCachedModels(): array
    {
        $models = [];
        $appPath = app_path('Models');

        if (!is_dir($appPath)) {
            return $models;
        }

        $files = glob($appPath . '/*.php');

        foreach ($files as $file) {
            $className = 'App\\Models\\' . basename($file, '.php');

            if (class_exists($className)) {
                $traits = class_uses_recursive($className);
                if (in_array('Snowsoft\LaravelModelCaching\Traits\Cachable', $traits)) {
                    $models[] = $className;
                }
            }
        }

        return $models;
    }
}
