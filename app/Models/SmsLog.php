<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SmsLog extends Model
{
    protected $table = 'smslog';

    // 兼容旧表字段，时间戳仍由业务层显式维护。
    public $timestamps = false;

    protected $guarded = ['id'];
}
