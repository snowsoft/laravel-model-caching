<?php

namespace Snowsoft\LaravelModelCaching\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Cache Hit Event
 *
 * Fired when a cache hit occurs
 */
class CacheHit
{
    use Dispatchable, SerializesModels;

    public $key;
    public $model;
    public $tags;

    public function __construct(string $key, $model, array $tags = [])
    {
        $this->key = $key;
        $this->model = $model;
        $this->tags = $tags;
    }
}
