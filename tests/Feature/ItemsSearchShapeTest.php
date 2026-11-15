<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * 列表与搜索在真实脏数据形态下的回归。
 *
 * 2026-11-15 线上样式问题：患者挂靠的医生已被软删除时，列表映射表取不到键，
 * 触发 Undefined array key 并返回 500。这里固定软删除父记录、父记录缺失和空值三种形态。
 */
final class ItemsSearchShapeTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        DB::table('hospital')->insert([['id' => 1, 'title' => '测试医院甲']]);
        DB::table('user')->insert([
            'id'       => 1, 'hospitalid' => 1, 'realname' => '在职医生', 'mobile' => '13800000001',
            'password' => Hash::make('test-password'), 'salt' => '',
        ]);
        DB::table('user')->insert([
            'id'       => 8, 'hospitalid' => 1, 'realname' => '离职医生', 'mobile' => '13800000008',
            'password' => Hash::make('test-password'), 'salt' => '', 'deleted_at' => now(),
        ]);

        $user = User::findOrFail(1);
        $this->actingAs($user)->withSession(['auth_password' => $user->password]);
    }

    public function test_items_tolerate_soft_deleted_and_missing_doctor(): void
    {
        // 医生 8 已软删除；医生 999 根本不存在；患者 1 还带空身份证和空随访日。
        $this->insertPatient(['id' => 1, 'realname' => '住院甲', 'doctorid' => 8, 'isin' => 1, 'idcard' => '', 'nextfollowupday' => null]);
        $this->insertPatient(['id' => 2, 'realname' => '住院乙', 'doctorid' => 999, 'isin' => 1]);
        $this->insertPatient(['id' => 3, 'realname' => '门诊丙', 'doctorid' => 8, 'isout' => 1]);

        $this->getJson('/inhospital/items?sort=id&order=ascending')
            ->assertOk()
            ->assertJsonPath('data.total', 2)
            ->assertJsonPath('data.items.0.recorder', '离职医生(已删除)')
            ->assertJsonPath('data.items.0.hospital', '测试医院甲')
            ->assertJsonPath('data.items.1.recorder', '')
            ->assertJsonPath('data.items.0.progress.hospsituation', false);

        $this->getJson('/outpatient/items?sort=id&order=ascending')
            ->assertOk()
            ->assertJsonPath('data.total', 1)
            ->assertJsonPath('data.items.0.recorder', '离职医生(已删除)');
    }

    public function test_items_search_matches_name_and_hospcode_branches(): void
    {
        $this->insertPatient(['id' => 11, 'realname' => '朱一', 'doctorid' => 1, 'isin' => 1, 'hospcode' => 'A1001']);
        $this->insertPatient(['id' => 12, 'realname' => '王二', 'doctorid' => 1, 'isin' => 1, 'hospcode' => '100120']);

        // 中文关键词走姓名 LIKE。
        $this->getJson('/inhospital/items?realname='.urlencode('朱'))
            ->assertOk()
            ->assertJsonPath('data.total', 1)
            ->assertJsonPath('data.items.0.patientid', 11);

        // 字母/数字关键词走住院号 LIKE。
        $this->getJson('/inhospital/items?realname=A1001')
            ->assertOk()
            ->assertJsonPath('data.total', 1)
            ->assertJsonPath('data.items.0.patientid', 11);

        $this->getJson('/inhospital/items?realname=100120')
            ->assertOk()
            ->assertJsonPath('data.total', 1)
            ->assertJsonPath('data.items.0.patientid', 12);

        // 无命中时返回空列表而不是报错。
        $this->getJson('/inhospital/items?realname='.urlencode('不存在'))
            ->assertOk()
            ->assertJsonPath('data.total', 0)
            ->assertJsonPath('data.items', []);
    }

    public function test_items_invalid_sort_is_rejected_by_validation(): void
    {
        $this->getJson('/inhospital/items?sort=not_a_column&order=ascending')
            ->assertStatus(422)
            ->assertJsonPath('code', 1);
    }

    /**
     * @param  array<string,mixed>  $row
     */
    private function insertPatient(array $row): void
    {
        $defaults               = array_fill_keys(['linkman', 'linkrel', 'linkman2', 'linkrel2', 'linkman3', 'linkrel3'], '');
        $defaults['hospitalid'] = 1;

        DB::table('patient')->insert($row + $defaults);
    }
}
