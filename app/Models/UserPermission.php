<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\SoftDeletes;

class UserPermission extends BaseModel
{
    use SoftDeletes;

    protected $table = 'userpermission';
}
