<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Hospital extends Model
{
    protected $table = 'hospital';

    // 兼容旧表字段，时间戳仍由业务层显式维护。
    public $timestamps = false;

    protected $guarded = ['id'];
}
