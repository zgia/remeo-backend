<?php

namespace App\Service;

use App\Models\User;
use App\Models\UserPermission;
use App\Repositories\UserPermissionRepository;
use Illuminate\Support\Facades\DB;

/**
 * 医生。注：医生存放在user表
 */
class DoctorService
{
    public function __construct(
        protected readonly UserPermissionRepository $userPermissionRepository,
        protected readonly UserService $userService,
    ) {}

    public string $doctorFields = 'user.id AS doctorid,hospitalid,hospital.title AS hosptitle,mobile,realname';

    /**
     * 获取当前登录用户的权限
     *
     * @return array<string>
     */
    public function getUserPermissions(int $doctorid)
    {
        return $this->userPermissionRepository->codesForDoctor($doctorid);
    }

    /**
     * 获取当前登录用户的权限
     *
     * @param  array<string,mixed>  $user
     * @return array<string,mixed>
     */
    public function getPermissions(array $user)
    {
        $isOperator = isOperator($user);
        $isViewer   = isViewer($user);

        $userPermissions = $this->getUserPermissions($user['id']);

        // 路由权限
        $permissions = ['hospital' => $isOperator, 'doctor' => $isOperator, 'otherih' => $isViewer, 'otherop' => $isViewer];

        return [
            'isOperator'  => $isOperator,
            'isViewer'    => $isViewer,
            'canDownload' => isAdministrator() || in_array('download.patient', $userPermissions),
            'permissions' => $permissions,
        ];
    }

    /**
     * @return array<string,mixed>
     */
    public function items(string $realname, int $hospitalid = 0): array
    {
        $perpage = (int) getOption('perpage', 20);

        $conds = ['user.deletedat' => 0];
        // 搜索
        if ($realname) {
            $conds[] = ['user.realname', 'LIKE', '%'.$realname.'%'];
        }

        if (! isAdministrator()) {
            $conds['user.hospitalid'] = currentHospitalId();
        }

        $total = User::query()->where($conds)->count();

        $hs = User::query()
            ->where($conds)
            ->leftJoin('hospital', 'user.hospitalid', '=', 'hospital.id')
            ->select(explode(',', $this->doctorFields))
            ->orderBy('user.id', 'desc')
            ->forPage(currentPage(), $perpage)
            ->get()
            ->map(fn (User $record): array => $record->getAttributes())
            ->all();

        $userPermissions = UserPermission::query()
            ->whereIn('userpermission.doctorid', array_column($hs, 'doctorid'))
            ->where('userpermission.deletedat', 0)
            ->select('doctorid', 'permissioncode')
            ->get()
            ->map(fn (UserPermission $record): array => $record->getAttributes())
            ->all();
        $ups = [];
        foreach ($userPermissions as $up) {
            $ups[$up['doctorid']][] = $up['permissioncode'];
        }

        foreach ($hs as &$h) {
            $h['permissions'] = $ups[$h['doctorid']] ?? [];
        }

        return ['total' => $total, 'items' => $hs];
    }

    /**
     * @return array<string,mixed>
     */
    public function getDoctor(int $doctorid): array
    {
        $doctor = User::query()
            ->where('user.id', $doctorid)
            ->where('user.deletedat', 0)
            ->leftJoin('hospital', 'user.hospitalid', '=', 'hospital.id')
            ->select(explode(',', $this->doctorFields))
            ->first();
        $doctor                = $doctor?->getAttributes();
        $doctor['permissions'] = $this->getUserPermissions($doctorid);

        return $doctor;
    }

    /**
     * 更新
     *
     * @param  array<string,mixed>  $doctor
     * @return array<string,mixed>
     */
    public function update(array $doctor)
    {
        return DB::transaction(function () use ($doctor): array {
            $doctor['id'] = $doctor['doctorid'];
            $permissions  = $doctor['permissions'];
            unset($doctor['doctorid'], $doctor['permissions']);

            // 新增医生须先获得真实 ID，再将权限写入同一事务。
            $doctorid = $this->userService->saveUser($doctor);
            UserPermission::query()->where('userpermission.doctorid', $doctorid)->update(['deletedat' => timenow()]);
            foreach ($permissions as $code => $value) {
                if (in_array($value, [true, 1, '1', 'true'], true)) {
                    UserPermission::query()->insert([
                        'doctorid'  => $doctorid, 'permissioncode' => $code,
                        'createdat' => timenow(), 'updatedat' => timenow(),
                    ]);
                }
            }

            return $this->getDoctor($doctorid);
        });
    }

    /**
     * 删除
     */
    public function delete(int $doctorid): void
    {
        User::query()->where('user.id', $doctorid)->update(['deletedat' => timenow()]);
    }

    /**
     * 重置密码
     */
    public function resetInitPassword(int $doctorid): void
    {
        $doctor = User::query()->where('user.id', $doctorid)->select('mobile', 'salt')->first();
        $doctor = $doctor?->getAttributes();

        User::query()
            ->where('user.id', $doctorid)
            ->update([
                'password'  => UserService::getInitPassword($doctor['mobile'], $doctor['salt']),
                'updatedat' => timenow(),
            ]);
    }
}
