<?php namespace Snowsoft\LaravelModelCaching;

use GeneaLabs\LaravelPivotEvents\Traits\FiresPivotEventsTrait;
use Snowsoft\LaravelModelCaching\Traits\Buildable;
use Snowsoft\LaravelModelCaching\Traits\BuilderCaching;
use Snowsoft\LaravelModelCaching\Traits\Caching;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class CachedBelongsToMany extends BelongsToMany
{
    use Buildable;
    use BuilderCaching;
    use Caching;
    use FiresPivotEventsTrait;
}
