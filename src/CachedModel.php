<?php namespace Snowsoft\LaravelModelCaching;

use Snowsoft\LaravelModelCaching\Traits\Cachable;
use Illuminate\Database\Eloquent\Model;

abstract class CachedModel extends Model
{
    use Cachable;
}
