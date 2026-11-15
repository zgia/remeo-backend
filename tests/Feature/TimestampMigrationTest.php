<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class TimestampMigrationTest extends TestCase
{
    public function test_timestamps_are_backfilled_and_legacy_integer_columns_are_dropped(): void
    {
        if (DB::connection()->getDriverName() === 'mysql') {
            $this->markTestSkipped('迁移测试会执行 DDL，MySQL 临时表模式无法依赖事务回滚。');
        }

        $migration = require database_path('migrations/2026_11_15_000000_add_datetime_timestamps_to_registry_tables.php');

        // 回到旧结构，准备历史数据后再执行一次迁移，验证回填与字段重建。
        $migration->down();

        DB::table('hospital')->insert([
            ['id' => 1, 'title' => '有真实时间', 'createdat' => 1700000000, 'updatedat' => 0, 'deletedat' => 1],
            ['id' => 2, 'title' => '无时间', 'createdat' => 0, 'updatedat' => 1700000000, 'deletedat' => 0],
        ]);
        DB::table('smslog')->insert([
            'userid'    => 1, 'mobile' => '13800000001', 'msg' => '内容', 'issent' => 1,
            'createdat' => 1700000000, 'updatedat' => 0,
        ]);
        DB::table('log')->insert([
            'type'      => 'test', 'objectid' => 1, 'script' => 'script', 'action' => 'update',
            'ipaddress' => '127.0.0.1', 'userid' => 1, 'fromcontent' => '{}', 'tocontent' => '{}',
            'createdat' => 1700000000,
        ]);
        DB::table('smscode')->insert([
            'userid'    => 1, 'mobile' => '13800000001', 'smscode' => '123456',
            'createdat' => 1700000000, 'smsday' => 20231115,
        ]);
        DB::table('category')->insert(['id' => 1, 'title' => '字典']);

        $migration->up();

        $hospital = DB::table('hospital')->where('id', 1)->first();
        $this->assertSame('2023-11-15 06:13:20', $hospital->created_at);
        // 旧值 0 的行由字段默认值补为迁移时间（与 MySQL 的 DEFAULT CURRENT_TIMESTAMP 一致）。
        $this->assertNotNull($hospital->updated_at);
        $this->assertSame('2026-01-01 12:12:12', $hospital->deleted_at);

        $empty = DB::table('hospital')->where('id', 2)->first();
        $this->assertNotNull($empty->created_at);
        $this->assertSame('2023-11-15 06:13:20', $empty->updated_at);
        $this->assertNull($empty->deleted_at);

        $smslog = DB::table('smslog')->first();
        $this->assertSame('2023-11-15 06:13:20', $smslog->created_at);
        $this->assertNotNull($smslog->updated_at);

        // 没有旧时间字段的表只补 Laravel 时间字段，历史行按迁移时间补齐。
        $this->assertNotNull(DB::table('log')->value('updated_at'));
        $this->assertSame('2023-11-15 06:13:20', DB::table('smscode')->value('created_at'));
        $this->assertNotNull(DB::table('smscode')->value('updated_at'));
        $this->assertNotNull(DB::table('category')->value('created_at'));

        // 旧字段已删除，新字段全部就位。
        $this->assertTrue(Schema::hasColumn('hospital', 'deleted_at'));
        $this->assertFalse(Schema::hasColumn('hospital', 'deletedat'));
        $this->assertFalse(Schema::hasColumn('hospital', 'createdat'));
        $this->assertFalse(Schema::hasColumn('hospital', 'updatedat'));
        $this->assertTrue(Schema::hasColumn('category', 'created_at'));
        $this->assertTrue(Schema::hasColumn('progress', 'updated_at'));
    }
}
