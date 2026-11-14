<?php

namespace App\Controller;

use App\Enums\SituationType;
use App\Http\Requests\CheckRequest;
use App\Http\Requests\OutpatientUpdatePatientRequest;
use App\Http\Requests\OutpatientUpdateSituationRequest;
use App\Http\Requests\PatientListRequest;
use App\Service\ExportOutpatientService;
use App\Service\OutpatientService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * 门诊患者
 */
class OutpatientController extends PatientController
{
    /**
     * 构造函数
     */
    public function __construct(private readonly OutpatientService $outpatientService, Request $request)
    {
        parent::__construct($outpatientService, $request);
        $this->outpatientService->setHospseqZero();
    }

    public function index(PatientListRequest $request): JsonResponse
    {
        $realname   = (string) $request->query('realname', '');
        $hospitalid = $request->integer('hospitalid');
        $sort       = (string) $request->query('sort', '');
        $order      = (string) $request->query('order', '');

        $data = $this->outpatientService->items($realname, $hospitalid, $sort, $order);

        return $this->success('患者列表', $data);
    }

    public function download(ExportOutpatientService $export): BinaryFileResponse
    {
        if ($this->patientid) {
            $export->getPatientById($this->patientid);
        }

        return $export->download();
    }

    /**
     * 更新
     */
    public function updatePatient(OutpatientUpdatePatientRequest $request): JsonResponse
    {
        $data = $request->validated();

        if ($this->patientid) {
            $patient = $this->outpatientService->updatePatient($data);
        } else {
            $patient = $this->outpatientService->addPatient($data);
        }

        return $this->success('患者添加成功。', $patient);

    }

    /**
     * 患者
     */
    public function getPatientData(): JsonResponse
    {
        return $this->success('成功获取患者信息。', $this->outpatientService->getPatient());

    }

    /**
     * 删除患者
     */
    public function deletePatient(): JsonResponse
    {
        $this->outpatientService->deletePatient();

        return $this->success('成功删除患者。');

    }

    /**
     * 首诊情况
     */
    public function updateSituation(OutpatientUpdateSituationRequest $request): JsonResponse
    {
        $data = $request->validated();

        $situation = $this->outpatientService->updateSituation($data);

        return $this->success('患者首诊记录添加成功。', $situation);

    }

    /**
     * 首诊情况
     */
    public function getSituationData(): JsonResponse
    {
        $situation = $this->outpatientService->getSituation(0, SituationType::OpFisrt->value);

        return $this->success('患者首诊记录', $situation);

    }

    /**
     * 复查
     */
    public function updateCheck(CheckRequest $request): JsonResponse
    {
        $data = $this->outpatientService->updateCheck($request->validated());

        return $this->success('患者复查记录保存成功。', $data);

    }

    /**
     * 复查
     */
    public function getCheck(): JsonResponse
    {
        $treatmentid = request()->integer('treatmentid');

        $treatment = $this->outpatientService->getCheck($treatmentid);

        return $this->success('患者复查记录', $treatment);

    }

    /**
     * 患者门诊首页
     */
    public function getHomepage(): JsonResponse
    {
        $data = $this->outpatientService->getHomepage();

        return $this->success('患者首页', $data);

    }

    /**
     * 复查次数
     */
    public function getCheckCount(): JsonResponse
    {
        $data = ['patientid' => $this->patientid];

        $data['checkcount'] = $this->outpatientService->getCheckCount();

        return $this->success('患者复查次数', $data);

    }

    /**
     * 患者复查记录
     */
    public function getCheckpage(): JsonResponse
    {
        $treatmentid = request()->integer('treatmentid');

        $data = $this->outpatientService->getCheckpage($treatmentid);

        return $this->success('患者复查记录', $data);

    }

    /**
     * 获取患者的复查记录，提供给前台筛选
     */
    public function getCheckDayList(): JsonResponse
    {
        $data = $this->outpatientService->getCheckDayList();

        return $this->success('患者复查记录', $data);

    }

    /**
     * 办理住院
     */
    public function hospitalized(): JsonResponse
    {
        $undo = request()->boolean('undo');

        $this->outpatientService->hospitalized($undo);

        return $this->success('患者成功标记住院。');

    }

    /**
     * 是否住院
     */
    public function isHospitalized(): JsonResponse
    {
        $patient = $this->outpatientService->getPatient();

        return $this->success('患者住院状态。', ['hospcode' => $patient['hospcode'] ?? '', 'isin' => $patient['isin']]);

    }
}
