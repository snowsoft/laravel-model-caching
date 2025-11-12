<?php

namespace Snowsoft\LaravelModelCaching\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Cache Benchmark Command
 *
 * Benchmark cache performance
 */
class Benchmark extends Command
{
    protected $signature = 'modelCache:benchmark
                            {--model= : Benchmark specific model}
                            {--iterations=100 : Number of iterations}
                            {--warmup=10 : Warmup iterations}
                            {--no-cache : Run without cache for comparison}';

    protected $description = 'Benchmark cache performance';

    public function handle()
    {
        $model = $this->option('model');
        $iterations = (int) $this->option('iterations');
        $warmup = (int) $this->option('warmup');
        $noCache = $this->option('no-cache');

        if (!$model) {
            $this->error('Please specify --model option.');
            return 1;
        }

        if (!class_exists($model)) {
            $this->error("Model '{$model}' not found.");
            return 1;
        }

        $instance = new $model;

        if (!method_exists($instance, 'makeCacheTags')) {
            $this->error("Model '{$model}' does not use Cachable trait.");
            return 1;
        }

        $this->info("⚡ Benchmarking: {$model}");
        $this->info("Iterations: {$iterations}");
        $this->newLine();

        // Warmup
        if ($warmup > 0) {
            $this->info("Warming up ({$warmup} iterations)...");
            for ($i = 0; $i < $warmup; $i++) {
                $instance::all();
            }
        }

        // Benchmark with cache
        if (!$noCache) {
            $this->info("Benchmarking with cache...");
            $withCache = $this->runBenchmark($model, $iterations);
        }

        // Benchmark without cache
        $this->info("Benchmarking without cache...");
        config(['laravel-model-caching.enabled' => false]);
        $withoutCache = $this->runBenchmark($model, $iterations);
        config(['laravel-model-caching.enabled' => true]);

        // Results
        $this->newLine();
        $this->info('📊 Results:');
        $this->newLine();

        if (!$noCache) {
            $speedup = $withoutCache['time'] / $withCache['time'];
            $improvement = (($withoutCache['time'] - $withCache['time']) / $withoutCache['time']) * 100;

            $this->table(
                ['Metric', 'With Cache', 'Without Cache', 'Improvement'],
                [
                    ['Time (ms)', number_format($withCache['time'], 2), number_format($withoutCache['time'], 2), number_format($improvement, 1) . '%'],
                    ['Queries', $withCache['queries'], $withoutCache['queries'], number_format((($withoutCache['queries'] - $withCache['queries']) / $withoutCache['queries']) * 100, 1) . '%'],
                    ['Memory (MB)', number_format($withCache['memory'], 2), number_format($withoutCache['memory'], 2), '-'],
                ]
            );

            $this->newLine();
            $this->info("🚀 Speedup: " . number_format($speedup, 2) . "x faster");
        } else {
            $this->table(
                ['Metric', 'Value'],
                [
                    ['Time (ms)', number_format($withoutCache['time'], 2)],
                    ['Queries', $withoutCache['queries']],
                    ['Memory (MB)', number_format($withoutCache['memory'], 2)],
                ]
            );
        }

        return 0;
    }

    protected function runBenchmark(string $model, int $iterations): array
    {
        $startTime = microtime(true);
        $startMemory = memory_get_usage(true);
        $startQueries = count(DB::getQueryLog());

        DB::enableQueryLog();

        for ($i = 0; $i < $iterations; $i++) {
            $model::all();
        }

        $endTime = microtime(true);
        $endMemory = memory_get_usage(true);
        $endQueries = count(DB::getQueryLog());

        return [
            'time' => ($endTime - $startTime) * 1000, // Convert to milliseconds
            'queries' => $endQueries - $startQueries,
            'memory' => ($endMemory - $startMemory) / 1024 / 1024, // Convert to MB
        ];
    }
}
