<?php

namespace App\Controller;

use App\Http\Requests\CheckRequest;
use App\Http\Requests\InhospitalUpdateCodeRequest;
use App\Http\Requests\InhospitalUpdateInitialStateRequest;
use App\Http\Requests\InhospitalUpdatePathologyRequest;
use App\Http\Requests\InhospitalUpdatePatientRequest;
use App\Http\Requests\InhospitalUpdateSituationRequest;
use App\Http\Requests\InhospitalUpdateSurgeryRequest;
use App\Http\Requests\PatientListRequest;
use App\Service\ExportInhospitalService;
use App\Service\InhospitalService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * 住院患者
 */
class InhospitalController extends PatientController
{
    /**
     * 构造函数
     */
    public function __construct(private readonly InhospitalService $inhospitalService, Request $request)
    {
        parent::__construct($inhospitalService, $request);
    }

    /**
     * 患者列表
     */
    public function index(PatientListRequest $request): JsonResponse
    {
        $realname   = (string) $request->query('realname', '');
        $hospitalid = $request->integer('hospitalid');
        $sort       = (string) $request->query('sort', '');
        $order      = (string) $request->query('order', '');

        $data = $this->inhospitalService->items($realname, $hospitalid, $sort, $order);

        return $this->success('患者列表', $data);
    }

    public function download(ExportInhospitalService $export): BinaryFileResponse
    {
        if ($this->patientid) {
            $export->getPatientById($this->patientid);
        }

        return $export->download();
    }

    /**
     * 更新患者信息
     */
    public function updatePatient(InhospitalUpdatePatientRequest $request): JsonResponse
    {
        $data = $request->validated();

        if ($this->patientid) {
            $patient = $this->inhospitalService->updatePatient($data);
        } else {
            $patient = $this->inhospitalService->addPatient($data);
        }

        return $this->success('患者添加成功。', $patient);

    }

    /**
     * 获取患者信息
     */
    public function getPatientData(): JsonResponse
    {
        return $this->success('成功获取患者信息。', $this->inhospitalService->getPatient());

    }

    /**
     * 删除患者
     */
    public function deletePatient(): JsonResponse
    {
        $this->inhospitalService->deletePatient();

        return $this->success('成功删除患者。');

    }

    /**
     * 患者再次住院
     */
    public function inhospAgain(): JsonResponse
    {
        $seq = $this->inhospitalService->inhospAgain();

        return $this->success('患者成功办理再次住院。', ['patientid' => $this->patientid, 'hospseq' => $seq]);

    }

    /**
     * 更新住院号
     */
    public function updateCode(InhospitalUpdateCodeRequest $request): JsonResponse
    {
        $data = $request->validated();

        $this->inhospitalService->updateCode($data);

        return $this->success('更新患者住院号成功。', $data);

    }

    /**
     * 患者初始状态
     */
    public function updateInitialState(InhospitalUpdateInitialStateRequest $request): JsonResponse
    {
        $data = $request->validated();

        $this->inhospitalService->updateInitialState($data['data']);

        return $this->success('患者初始状态保存成功。', $data);

    }

    /**
     * 患者初始状态
     */
    public function getInitialStateData(): JsonResponse
    {
        $data = $this->inhospitalService->getInitialState();

        return $this->success('患者初始状态获取成功。', $data);

    }

    /**
     * 患者入院情况
     */
    public function updateSituation(InhospitalUpdateSituationRequest $request): JsonResponse
    {
        $data = $request->validated();

        $situation = $this->inhospitalService->updateSituation($data);

        return $this->success('患者入院情况保存成功。', $situation);

    }

    /**
     * 患者入院情况
     */
    public function getSituationData(): JsonResponse
    {
        // i 和 d
        $comefrom = (string) request()->query('comefrom', '');

        $situation = $this->inhospitalService->getSituation(0, $comefrom);

        return $this->success('患者入院情况', $situation);

    }

    /**
     * 更细手术记录
     */
    public function updateSurgery(InhospitalUpdateSurgeryRequest $request): JsonResponse
    {
        $data = $request->validated();

        // 术后特殊情况
        if ($data['surgeryafter']) {
            $surgery = $this->inhospitalService->updateSurgeryAfter($data);
        } else {
            unset($data['surgeryafter']);
            $surgery = $this->inhospitalService->updateSurgery($data);
        }

        return $this->success('患者手术信息保存成功。', $surgery);

    }

    /**
     * 手术记录
     */
    public function getSurgeryData(): JsonResponse
    {
        $surgery = $this->inhospitalService->getSurgery();

        return $this->success('患者手术记录', $surgery);

    }

    /**
     * 病理
     */
    public function updatePathology(InhospitalUpdatePathologyRequest $request): JsonResponse
    {
        $data = $request->validated();

        $pathology = $this->inhospitalService->updatePathology($data);

        return $this->success('患者病理信息保存成功。', $pathology);

    }

    /**
     * 病理
     */
    public function getPathologyData(): JsonResponse
    {
        $pathology = $this->inhospitalService->getPathology();

        return $this->success('患者病理记录', $pathology);

    }

    /**
     * 随访
     */
    public function updateFollowup(CheckRequest $request): JsonResponse
    {
        $followup = $this->inhospitalService->updateFollowup($request->validated());

        return $this->success('患者随访信息保存成功。', $followup);

    }

    /**
     * 随访
     */
    public function getFollowup(): JsonResponse
    {
        $treatmentid = request()->integer('treatmentid');

        $followup = $this->inhospitalService->getFollowup($treatmentid);

        return $this->success('患者随访记录', $followup);

    }

    /**
     * 随访
     */
    public function getFollowupCount(): JsonResponse
    {
        $data = ['patientid' => $this->patientid];

        $data['followupcount'] = $this->inhospitalService->getFollowupCount();

        return $this->success('患者随访次数', $data);

    }

    /**
     * 患者首页记录
     */
    public function getHomePage(): JsonResponse
    {
        $hospseq = request()->integer('hospseq');

        $data = $this->inhospitalService->getHomePage($hospseq);

        return $this->success('患者首页', $data);

    }

    /**
     * 获取患者的住院日期，提供给前台筛选
     */
    public function getHomePageDayList(): JsonResponse
    {
        $data = $this->inhospitalService->getHomePageDayList();

        return $this->success('患者住院记录', $data);

    }

    /**
     * 患者随访记录
     */
    public function getFollowupPage(): JsonResponse
    {
        $treatmentid = request()->integer('treatmentid');

        $data = $this->inhospitalService->getFollowupPage($treatmentid);

        return $this->success('患者随访记录', $data);

    }

    /**
     * 获取患者的复查记录，提供给前台筛选
     */
    public function getFollowupDayList(): JsonResponse
    {
        $data = $this->inhospitalService->getFollowupDayList();

        return $this->success('患者随访记录', $data);

    }
}
