<?php namespace Snowsoft\LaravelModelCaching\Traits;

use GeneaLabs\LaravelPivotEvents\Traits\PivotEventTrait;

trait Cachable
{
    use Caching;
    use ModelCaching;
    use BulkOperations;
    use PivotEventTrait {
        ModelCaching::newBelongsToMany insteadof PivotEventTrait;
    }
}
