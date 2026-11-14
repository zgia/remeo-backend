<?php

namespace App\Controller;

use App\Http\Requests\HospitalUpdateHospitalRequest;
use App\Service\HospitalService;
use Illuminate\Http\JsonResponse;

/**
 * 医院
 */
class HospitalController extends BaseController
{
    /**
     * 构造函数
     */
    public function __construct(private readonly HospitalService $hospitalService) {}

    /**
     * IndexController
     */
    public function index(): JsonResponse
    {
        $title = (string) request()->query('title', '');
        $data  = $this->hospitalService->items($title);

        return $this->success('医院列表', $data);
    }

    /**
     * All Hospitals
     */
    public function all(): JsonResponse
    {
        $data = $this->hospitalService->getAllItems();

        return $this->success('医院列表', $data);
    }

    /**
     * 编辑
     */
    public function updateHospital(HospitalUpdateHospitalRequest $request): JsonResponse
    {
        $hospital = $request->validated();

        $hospital = $this->hospitalService->update($hospital);

        return $this->success('医院信息保存成功。', $hospital);

    }

    /**
     * 删除
     */
    public function deleteHospital(): JsonResponse
    {
        $hospitalid = request()->integer('hospitalid');

        $this->hospitalService->delete($hospitalid);

        return $this->success('删除医院成功。', ['hospitalid' => $hospitalid]);

    }
}
