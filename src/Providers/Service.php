<?php

namespace Snowsoft\LaravelModelCaching\Providers;

use Snowsoft\LaravelModelCaching\Console\Commands\Clear;
use Snowsoft\LaravelModelCaching\Console\Commands\Publish;
use Snowsoft\LaravelModelCaching\Console\Commands\Stats;
use Snowsoft\LaravelModelCaching\Console\Commands\Health;
use Snowsoft\LaravelModelCaching\Console\Commands\Debug;
use Snowsoft\LaravelModelCaching\Console\Commands\Warm;
use Snowsoft\LaravelModelCaching\Console\Commands\Benchmark;
use Snowsoft\LaravelModelCaching\Helper;
use Snowsoft\LaravelModelCaching\ModelCaching;
use Illuminate\Support\ServiceProvider;

class Service extends ServiceProvider
{
    protected $defer = false;

    public function boot()
    {
        $configPath = __DIR__ . '/../../config/laravel-model-caching.php';
        $this->mergeConfigFrom($configPath, 'laravel-model-caching');
        $this->commands([
            Clear::class,
            Publish::class,
            Stats::class,
            Health::class,
            Debug::class,
            Warm::class,
            Benchmark::class,
        ]);
        $this->publishes([
            $configPath => config_path('laravel-model-caching.php'),
        ], "config");
    }

    public function register()
    {
        if (! class_exists('Snowsoft\LaravelModelCaching\EloquentBuilder')) {
            class_alias(
                ModelCaching::builder(),
                'Snowsoft\LaravelModelCaching\EloquentBuilder'
            );
        }

        $this->app->bind("model-cache", Helper::class);
        $this->app->singleton("model-cache.tenant-resolver", function () {
            return new \Snowsoft\LaravelModelCaching\TenantResolver();
        });
        $this->app->singleton(\Snowsoft\LaravelModelCaching\Services\SelectiveCacheInvalidator::class);
        $this->app->singleton(\Snowsoft\LaravelModelCaching\Services\CacheRefreshService::class);
        $this->app->singleton(\Snowsoft\LaravelModelCaching\Services\SearchCacheService::class);
        $this->app->singleton(\Snowsoft\LaravelModelCaching\Services\UpdateCacheService::class);
    }
}
