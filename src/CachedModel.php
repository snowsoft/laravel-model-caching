<?php namespace Snowsoft\LaravelModelCaching;

use Snowsoft\LaravelModelCaching\Traits\Cachable;
use Snowsoft\LaravelModelCaching\Traits\BulkOperations;
use Illuminate\Database\Eloquent\Model;

abstract class CachedModel extends Model
{
    use Cachable;
    use BulkOperations;
}
