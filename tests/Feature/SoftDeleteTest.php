<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Hospital;
use App\Models\SmsCode;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SoftDeleteTest extends TestCase
{
    public function test_soft_deletes_scope_and_restore_are_handled_by_laravel(): void
    {
        DB::table('hospital')->insert([
            ['id' => 1, 'title' => '有效医院', 'deleted_at' => null],
            ['id' => 2, 'title' => '已删除医院', 'deleted_at' => '2026-01-01 12:12:12'],
        ]);

        $this->assertSame(1, Hospital::query()->count());
        $this->assertSame(2, Hospital::query()->withTrashed()->count());
        $this->assertSame([2], Hospital::query()->onlyTrashed()->pluck('id')->all());

        $hospital = Hospital::query()->findOrFail(1);
        $this->assertFalse($hospital->trashed());
        $this->assertTrue($hospital->delete());
        $this->assertTrue($hospital->trashed());
        $this->assertNull(Hospital::query()->find(1));
        $this->assertNotNull(Hospital::query()->withTrashed()->find(1));

        $this->assertTrue($hospital->restore());
        $this->assertFalse($hospital->trashed());
        $this->assertNotNull(Hospital::query()->find(1));
    }

    public function test_models_without_deleted_at_keep_hard_delete(): void
    {
        DB::table('smscode')->insert([
            'userid'     => 1, 'mobile' => '13800000001', 'smscode' => '123456',
            'created_at' => now(), 'updated_at' => now(), 'smsday' => 1,
        ]);

        SmsCode::query()->where('mobile', '13800000001')->delete();

        $this->assertSame(0, DB::table('smscode')->count());
    }
}
