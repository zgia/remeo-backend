<?php

namespace App\Controller;

use App\Http\Requests\DoctorUpdateDoctorRequest;
use App\Service\DoctorService;
use Illuminate\Http\JsonResponse;

/**
 * 医生。注：医生存放在user表
 */
class DoctorController extends BaseController
{
    /**
     * 构造函数
     */
    public function __construct(private readonly DoctorService $doctorService) {}

    public function index(): JsonResponse
    {
        $title      = (string) request()->query('title', '');
        $hospitalid = request()->integer('hospitalid');

        $data = $this->doctorService->items($title, $hospitalid);

        return $this->success('医院列表', $data);
    }

    /**
     * 编辑
     */
    public function updateDoctor(DoctorUpdateDoctorRequest $request): JsonResponse
    {
        $doctor = $request->validated();
        $doctor = $this->doctorService->update($doctor);

        return $this->success('医院信息保存成功。', $doctor);

    }

    /**
     * 删除
     */
    public function deleteDoctor(): JsonResponse
    {
        $doctorid = request()->integer('doctorid');

        $this->doctorService->delete($doctorid);

        return $this->success('删除医院成功。', ['doctorid' => $doctorid]);

    }

    /**
     * 重置密码
     */
    public function resetPassword(): JsonResponse
    {
        $doctorid = request()->integer('doctorid');

        $this->doctorService->resetInitPassword($doctorid);

        return $this->success('密码重置成功。', ['doctorid' => $doctorid]);

    }
}
