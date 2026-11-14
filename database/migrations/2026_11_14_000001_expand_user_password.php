<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user', function (Blueprint $table): void {
            $table->string('password', 255)->default('')->comment('Laravel 密码散列，兼容待升级的旧密码')->change();
        });
    }

    public function down(): void
    {
        // 散列不可还原，回滚代码时保留字段宽度，防止截断已升级密码。
    }
};
