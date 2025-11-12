<?php namespace Snowsoft\LaravelModelCaching;

use Snowsoft\LaravelModelCaching\Traits\Buildable;
use Snowsoft\LaravelModelCaching\Traits\BuilderCaching;
use Snowsoft\LaravelModelCaching\Traits\Caching;
use Snowsoft\LaravelModelCaching\Traits\Searchable;
use Snowsoft\LaravelModelCaching\Traits\QueryExtensions;

class CachedBuilder extends EloquentBuilder
{
    use Buildable;
    use BuilderCaching;
    use Caching;
    use Searchable;
    use QueryExtensions;
}
