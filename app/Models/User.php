<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Auth\User as Authenticatable;

/** 使用既有医生表，由 Laravel 管理身份。 */
class User extends Authenticatable
{
    protected $table = 'user';

    public $timestamps = false;

    protected $guarded = ['id'];

    protected $hidden = ['password', 'salt'];

    protected $rememberTokenName = '';

    protected static function booted(): void
    {
        static::addGlobalScope('active', fn (Builder $query) => $query->where($query->qualifyColumn('deletedat'), 0));
    }
}
