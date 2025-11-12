<?php

namespace Snowsoft\LaravelModelCaching\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Cache Invalidated Event
 *
 * Fired when cache is invalidated
 */
class CacheInvalidated
{
    use Dispatchable, SerializesModels;

    public $model;
    public $tags;
    public $reason;

    public function __construct($model, array $tags = [], string $reason = '')
    {
        $this->model = $model;
        $this->tags = $tags;
        $this->reason = $reason;
    }
}
