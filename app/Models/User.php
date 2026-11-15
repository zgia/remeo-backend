<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;

/** 使用既有医生表，由 Laravel 管理身份。 */
class User extends Authenticatable
{
    use SoftDeletes;

    protected $table = 'user';

    protected $guarded = ['id'];

    protected $hidden = ['password', 'salt'];

    protected $rememberTokenName = '';
}
