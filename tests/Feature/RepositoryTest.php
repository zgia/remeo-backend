<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use App\Repositories\HospitalRepository;
use App\Repositories\LogRepository;
use App\Repositories\UserPermissionRepository;
use App\Repositories\UserRepository;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class RepositoryTest extends TestCase
{
    public function test_user_repository_preserves_internal_credentials_and_filters_deleted_accounts(): void
    {
        $repository = app(UserRepository::class);
        $id         = $repository->createUser([
            'mobile' => '13800000021', 'password' => '测试密码摘要', 'salt' => 'abcdef',
        ]);
        $deletedId = $repository->createUser([
            'mobile' => '13800000022', 'password' => '已删除账户摘要', 'deletedat' => 1,
        ]);

        $user = $repository->getUserByMobile('13800000021');
        $this->assertSame($id, $user['id']);
        $this->assertSame('测试密码摘要', $user['password']);
        $this->assertSame('abcdef', $user['salt']);
        $this->assertSame([$id => $user], $repository->getUsersByUserids([$id, $deletedId]));
        $this->assertNull($repository->getUserByMobile('13800000022'));
        $this->assertNull($repository->getUserByMobile('不存在'));
        $this->assertSame([], $repository->getUserById($deletedId));
        $this->assertSame([], $repository->getUserByCond([]));
        $this->assertSame([], $repository->getUsersByUserids([]));

        $repository->updatePassword($id, '更新后的摘要');
        $this->assertSame('更新后的摘要', $repository->getUserById($id)['password']);
        $serialized = User::query()->findOrFail($id)->toArray();
        $this->assertArrayNotHasKey('password', $serialized);
        $this->assertArrayNotHasKey('salt', $serialized);
    }

    public function test_log_repository_preserves_decoded_content_keys_and_pagination(): void
    {
        $repository = app(LogRepository::class);
        $data       = [
            'type'   => 'patient', 'id' => 21, 'userid' => 1,
            'script' => 'outpatient/updatepatient', 'action' => 'update',
            'from'   => ['realname' => '原姓名', 'password' => '旧摘要', 'salt' => 'abcdef'],
            'to'     => ['realname' => '新姓名', 'password' => '新摘要', 'salt' => 'ghijkl'],
        ];
        $firstId  = $repository->record($data);
        $secondId = $repository->record($data);
        $detail   = $repository->getLogDetail($firstId);

        $this->assertSame($firstId, $detail['id']);
        $this->assertSame(['realname' => '原姓名'], $detail['fromcontent']);
        $this->assertSame(['realname' => '新姓名'], $detail['tocontent']);
        $this->assertSame([$secondId, $firstId], array_keys($repository->getLogs(['objectid' => 21])));
        $this->assertSame([$firstId => $detail], $repository->getLogs(['objectid' => 21], 1, 1));
        $this->assertSame([], $repository->getLogDetail($secondId + 1));
        $this->assertSame([], $repository->getLogs(['objectid' => 22]));
    }

    public function test_hospital_and_permission_repositories_keep_soft_delete_filters(): void
    {
        DB::table('hospital')->insert([
            ['id' => 21, 'title' => '有效医院', 'deletedat' => 0],
            ['id' => 22, 'title' => '已删除医院', 'deletedat' => 1],
        ]);
        DB::table('userpermission')->insert([
            ['doctorid' => 21, 'permissioncode' => 'download.patient', 'deletedat' => 0],
            ['doctorid' => 21, 'permissioncode' => 'deleted.permission', 'deletedat' => 1],
            ['doctorid' => 22, 'permissioncode' => 'other.permission', 'deletedat' => 0],
        ]);

        $hospital = app(HospitalRepository::class);
        $this->assertSame('有效医院', $hospital->titleFor(21));
        $this->assertNull($hospital->titleFor(22));
        $this->assertNull($hospital->titleFor(23));

        $permissions = app(UserPermissionRepository::class);
        $this->assertSame(['download.patient'], $permissions->codesForDoctor(21));
        $this->assertSame([], $permissions->codesForDoctor(23));
    }
}
