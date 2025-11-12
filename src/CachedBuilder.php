<?php namespace Snowsoft\LaravelModelCaching;

use Snowsoft\LaravelModelCaching\Traits\Buildable;
use Snowsoft\LaravelModelCaching\Traits\BuilderCaching;
use Snowsoft\LaravelModelCaching\Traits\Caching;

class CachedBuilder extends EloquentBuilder
{
    use Buildable;
    use BuilderCaching;
    use Caching;
}
