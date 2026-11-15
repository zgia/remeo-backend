<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** 旧表模型基线：表名由子类声明，时间戳与软删除按 Laravel 约定由框架维护。 */
abstract class BaseModel extends Model
{
    protected $guarded = ['id'];
}
