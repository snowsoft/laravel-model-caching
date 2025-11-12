<?php

namespace Snowsoft\LaravelModelCaching\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Database\Eloquent\Model;
use Snowsoft\LaravelModelCaching\Services\CacheRefreshService;

/**
 * Refresh Model Cache Job
 *
 * Arka planda model cache'lerini yenilemek için queue job'ı
 */
class RefreshModelCacheJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $modelClass;
    protected $modelId;
    protected $queries;

    /**
     * Create a new job instance.
     */
    public function __construct(string $modelClass, $modelId = null, array $queries = [])
    {
        $this->modelClass = $modelClass;
        $this->modelId = $modelId;
        $this->queries = $queries;
    }

    /**
     * Execute the job.
     */
    public function handle(CacheRefreshService $service): void
    {
        if (!class_exists($this->modelClass)) {
            return;
        }

        $model = $this->modelId
            ? $this->modelClass::find($this->modelId)
            : new $this->modelClass;

        if (!$model) {
            return;
        }

        $service->refreshModelCache($model, $this->queries);
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        if (function_exists('logger')) {
            logger()->error('Cache refresh job failed', [
                'model' => $this->modelClass,
                'id' => $this->modelId,
                'error' => $exception->getMessage(),
            ]);
        }
    }
}
