<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\SoftDeletes;

class Permission extends BaseModel
{
    use SoftDeletes;

    protected $table = 'permission';
}
