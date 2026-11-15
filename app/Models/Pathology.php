<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\SoftDeletes;

class Pathology extends BaseModel
{
    use SoftDeletes;

    protected $table = 'pathology';
}
