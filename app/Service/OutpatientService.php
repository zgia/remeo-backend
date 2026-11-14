<?php

namespace App\Service;

use App\Enums\ConstantType;
use App\Enums\SituationType;
use App\Enums\VideoType;
use App\Exception\PatientException;
use App\Helper\DateTimeHelper;
use App\Models\Hospital;
use App\Models\Patient;
use App\Models\Progress;
use App\Models\Situation;
use App\Models\Treatment;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Class OutpatientService
 */
class OutpatientService extends PatientService
{
    public string $situationFields = 'kps,cc,positive,uterinefibroids,menopause,pastmedicalhistory,otherdesc';

    public string $patientFields = 'doctorid,hospitalid,age,gender,idcard,realname,mobile,linkman,linkrel,mobile2,linkman2,linkrel2,mobile3,linkman3,linkrel3,firstday,isalive,nextcheckday,lastcheckday,checkcount,hospcode,hospinitialstate,recurrence';

    /**
     * @var string 删除标记
     */
    public string $deleteTag = 'deletedout';

    /**
     * 门诊的住院次数为0
     */
    public function setHospseqZero(): void
    {
        $this->patient['hospseq'] = 0;
    }

    /**
     * @see SituationType
     */
    public function getSituationType()
    {
        return SituationType::OpCheck->value;
    }

    /**
     * @see VideoType
     */
    public function getVideoType()
    {
        return VideoType::OpCheck->value;
    }

    /**
     * @see ConstantType
     */
    public function getOutInType()
    {
        return ConstantType::HospitalizedOutpatient->value;
    }

    /**
     * 复查次数加1
     */
    public function addCheckCount(int $patientid): void
    {
        Patient::query()->where('patient.id', $patientid)->increment('checkcount');
    }

    /**
     * 更新下次复查时间
     *
     * @param  array<string,mixed>  $data
     */
    public function updateNextCheckDay(string $day, int $patientid, array &$data = []): void
    {
        $nextday = DateTimeHelper::dayAddMonths($day, 12);

        $patient = [
            'lastcheckday' => $day,
            'nextcheckday' => $nextday,
            'updatedat'    => timenow(),
        ];

        Patient::query()->where('patient.id', $patientid)->update($patient);

        $data['lastcheckday'] = $patient['lastcheckday'];
        $data['nextcheckday'] = $patient['nextcheckday'];
    }

    /**
     * 标记最后治疗的ID，用于数据导出功能
     */
    public function setLastCheckId(int $treatmentid, int $patientid): void
    {
        Patient::query()->where('patient.id', $patientid)->update(['lastcheckid' => $treatmentid]);
    }

    /**
     * 门诊患者列表
     *
     * @param  int  $hospitalid  0表示获取本医院的患者，-1表示获取其他医院的患者
     * @return array<string,mixed>
     */
    public function items(string $realname, int $hospitalid = 0, string $sort = '', string $order = '')
    {
        $perpage = (int) getOption('perpage', 20);

        // 处理当前医院所有医生的患者
        $conds = ['patient.isout' => 1, 'patient.'.$this->deleteTag => 0, 'patient.deletedat' => 0];

        // -1表示来查看其他医院的患者
        if ($hospitalid == -1 && isViewer(UserService::user())) {
            $conds[] = ['patient.hospitalid', '!=', currentHospitalId()];
        } else {
            $conds['patient.hospitalid'] = currentHospitalId();
        }

        // 搜索
        if ($realname) {
            $conds[] = ['patient.realname', 'LIKE', '%'.$realname.'%'];
        }

        $total = Patient::query()->where($conds)->count();

        // 排序
        if ($order == 'descending') {
            $order = 'DESC';
        } elseif ($order == 'ascending') {
            $order = 'ASC';
        }

        $ps = Patient::query()
            ->where($conds)
            ->leftJoin('progress', 'progress.patientid', '=', 'patient.id')
            ->select('patient.*', 'patient.id AS patientid', 'firstsituation', 'firstvideo')
            ->orderBy($sort && $order ? 'patient.'.$sort : 'patient.id', $sort && $order ? $order : 'desc')
            ->forPage(currentPage(), $perpage)
            ->get()
            ->keyBy('id')
            ->map(fn (Patient $record): array => $record->getAttributes())
            ->all();

        $doctorids = array_unique(array_column($ps, 'doctorid'));
        $doctors   = User::query()
            ->whereIn('user.id', $doctorids)
            ->where('user.deletedat', 0)
            ->select('user.*')
            ->pluck('realname', 'id')
            ->all();

        $hospitalids = array_unique(array_column($ps, 'hospitalid'));
        $hospitals   = Hospital::query()
            ->whereIn('hospital.id', $hospitalids)
            ->where('hospital.deletedat', 0)
            ->select('hospital.*')
            ->pluck('title', 'id')
            ->all();

        $patients = [];
        foreach ($ps as $p) {
            $patients[] = [
                'patientid' => $p['patientid'],
                'hospital'  => $hospitals[$p['hospitalid']],
                'realname'  => $p['realname'],
                'age'       => $p['age'],
                'firstday'  => $p['firstday'],
                'progress'  => [
                    'firstsituation' => $p['firstsituation'] == 1 ? true : false,
                    'firstvideo'     => $p['firstvideo']     == 1 ? true : false,
                ],
                'checkcount'   => (int) $p['checkcount'],
                'lastcheckday' => $p['lastcheckday'],
                'nextcheckday' => $p['nextcheckday'],
                'isin'         => $p['isin'] == 1,
                'recorder'     => $doctors[$p['doctorid']],
                'isfemale'     => isFemale($p['gender']),
            ];
        }

        return ['total' => $total, 'items' => $patients];
    }

    /**
     * 患者门诊首页
     *
     * @return array<string,mixed>
     */
    public function getHomepage()
    {
        $patientid = $this->getPatientid();
        $patient   = $this->getPatient();

        $categories = $this->systemService->categoriesKV();

        // 一、基本信息，含什么时候住院的
        $patientid = $patient['patientid'];
        $this->patientForPage($patient);

        $data = ['patientid' => $patientid, 'patient' => $patient];

        // 二、首诊
        $situation             = $this->getSituation(0, SituationType::OpFisrt->value);
        $situation['isfemale'] = $patient['isfemale'];
        $this->situationForPage($situation, $categories);
        $data['situation'] = $situation;

        // 三、术前检查
        $video = $this->getVideo(0, VideoType::OpFisrt->value);
        $this->videoForPage($video, $categories);

        $data['video'] = $video;

        return $data;
    }

    /**
     * 添加
     *
     * @param  array<string,mixed>  $patient
     * @return array<string,mixed>
     */
    public function addPatient(array $patient)
    {
        // 同一患者可以在多个不同的医院
        $ps = Patient::query()
            ->where('patient.hospitalid', currentHospitalId())
            ->where('patient.realname', 'LIKE', $patient['realname'].'%')
            ->where('patient.deletedat', 0)
            ->select('id', 'idcard', 'realname', 'mobile', 'mobile2', 'mobile3')
            ->get()
            ->keyBy('id')
            ->map(fn (Patient $record): array => $record->getAttributes())
            ->all();

        $newms = array_filter([$patient['mobile'], $patient['mobile2'], $patient['mobile3']]);

        foreach ($ps as $p) {
            if ($patient['idcard'] && $p['idcard'] == $patient['idcard']) {
                throw new PatientException('已经有身份证相同的患者（'.$p['realname'].'）了。');
            }

            $ms = [$p['mobile'], $p['mobile2'], $p['mobile3']];

            $same = array_intersect($newms, array_filter($ms));

            if ($same) {
                throw new PatientException('已经有同名且联系方式一样的患者（'.$p['realname'].'）了。');
            }
        }

        // 通过2个参数：isout，isin来区分患者当前状态
        // 门诊，isout=1；isin=0
        // 住院，isout=0；isin=1
        // 门诊转住院，isout=1；isin=1
        $patient['isout'] = 1;

        $patient['doctorid']   = currentUserId();
        $patient['hospitalid'] = currentHospitalId();
        // 创建患者时，下一次检查时间是首诊时间加1年
        $patient['nextcheckday'] = DateTimeHelper::dayAddMonths($patient['firstday'], 12);

        $patient['createdat'] = timenow();

        $patient['patientid'] = Patient::query()->insertGetId($patient);

        $this->newProgress($patient['patientid']);

        // 保存日志
        $log = [
            'type'   => 'patient',
            'action' => 'insert',
            'to'     => $patient,
            'id'     => $patient['patientid'],
        ];
        $this->logRepository->record($log);

        return $patient;
    }

    /**
     * 更新
     *
     * @param  array<string,mixed>  $patient
     * @return array<string,mixed>
     */
    public function updatePatient(array $patient)
    {
        $patientid = $this->getPatientid();

        // 是否有相同的身份证
        if ($patient['idcard']) {
            $p = Patient::query()
                ->where('patient.idcard', $patient['idcard'])
                ->where('patient.id', '!=', $patientid)
                ->where('patient.deletedat', 0)
                ->select('id', 'idcard', 'realname')
                ->first();
            $p = $p?->getAttributes();

            if ($p) {
                throw new PatientException('已经有身份证相同的患者（'.$p['realname'].'）了。');
            }
        }

        // 更新患者时，下一次检查时间是首诊时间加1年
        $patient['nextcheckday'] = DateTimeHelper::dayAddMonths($patient['firstday'], 12);
        $patient['updatedat']    = timenow();

        Patient::query()->where('patient.id', $patientid)->update($patient);

        $patient['patientid'] = $patientid;

        // 保存日志
        $log = [
            'type'   => 'patient',
            'action' => 'update',
            'to'     => $patient,
            'id'     => $patientid,
        ];
        $this->logRepository->record($log);

        return $patient;
    }

    /**
     * 首诊情况
     *
     * 门诊的首诊情况，影像学检查这2项作为作为第一次的治疗记录，这是因为
     * 编辑复查记录时，加载上次内容，包括首诊记录
     *
     * @param  array<string,mixed>  $situation
     * @return array<string,mixed>
     */
    public function updateSituation(array $situation)
    {
        $patientid = $this->getPatientid();

        // 门诊的首诊情况，影像学检查这2项作为作为第一次的治疗记录，这是因为
        // 编辑复查记录时，加载上次内容，包括首诊记录
        $treatmentid = (int) Treatment::query()
            ->where('treatment.patientid', $patientid)
            ->where('treatment.outin', $this->getOutInType())
            ->where('treatment.step', 1)
            ->where('treatment.deletedat', 0)
            ->value('id');

        return DB::transaction(function () use ($patientid, $situation, $treatmentid): array {
            // 每个患者的首诊情况只有一条记录（stype='f'）
            $old = Situation::query()
                ->where('situation.patientid', $patientid)
                ->where('situation.stype', SituationType::OpFisrt->value)
                ->where('situation.deletedat', 0)
                ->select('situation.*')
                ->first();
            $old = $old?->getAttributes();

            // 已经有首诊情况，则是编辑
            if ($old) {
                $situation['updatedat'] = timenow();

                Situation::query()->where('situation.id', $old['id'])->update($situation);
            } else {
                if ($treatmentid) {
                    $situation['treatmentid'] = $treatmentid;
                } else {
                    $treatment = [
                        'step'      => 1,
                        'patientid' => $patientid,
                        'outin'     => $this->getOutInType(),
                        'createdat' => timenow(),
                    ];

                    $situation['treatmentid'] = Treatment::query()->insertGetId($treatment);
                }

                $situation['stype']     = SituationType::OpFisrt->value;
                $situation['createdat'] = timenow();

                Situation::query()->insert($situation);
            }

            // 更新完整度
            Progress::query()->where('progress.patientid', $situation['patientid'])->update(['firstsituation' => 1]);

            // 保存日志
            $log = [
                'type'   => 'situation',
                'action' => $old ? 'update' : 'insert',
                'from'   => $old,
                'to'     => $situation,
                'id'     => $patientid,
            ];
            $this->logRepository->record($log);

            return $situation;
        });
    }

    /**
     * 复查
     *
     *
     * @return array<string,mixed>
     */
    public function getCheck(int $treatmentid = 0)
    {
        $data = $this->getTreatment($treatmentid);
        if (! $data) {
            return [];
        }

        $check = ['treatmentid' => $data['treatmentid'], 'patientid' => $data['patientid']];

        foreach (['treatment', 'situation', 'video'] as $key) {
            if (array_key_exists($key, $data)) {
                $check[$key] = $data[$key] ?? [];
            }
        }

        return $check;
    }

    /**
     * 复查次数
     *
     * @return int
     */
    public function getCheckCount()
    {
        $patientid = $this->getPatientid();

        $count = Treatment::query()
            ->where('treatment.patientid', $patientid)
            ->where('treatment.outin', $this->getOutInType())
            ->where('treatment.deletedat', 0)
            ->count();

        return $count ? $count - 1 : 0;
    }

    /**
     * 患者复查记录
     *
     *
     * @return array<string,mixed>
     */
    public function getCheckpage(int $treatmentid)
    {
        return $this->checkForPage($treatmentid);
    }

    /**
     * 获取患者的复查记录，提供给前台筛选
     *
     * @return list<array<string,mixed>>
     */
    public function getCheckDayList()
    {
        $patientid = $this->getPatientid();

        return Treatment::query()
            ->where('treatment.patientid', $patientid)
            ->where('treatment.outin', $this->getOutInType())
            ->where('treatment.step', '<>', 1)
            ->where('treatment.deletedat', 0)
            ->select('id AS treatmentid', 'checkday')
            ->get()
            ->map(fn (Treatment $record): array => $record->getAttributes())
            ->all();
    }

    /**
     * 办理住院
     *
     * @param  bool  $undo  true表示取消办理住院，false表示办理住院
     */
    public function hospitalized(bool $undo = false): void
    {
        $patientid = $this->getPatientid();

        $data = [
            'isin'      => $undo ? 0 : 1,
            'hospseq'   => $undo ? 0 : 1,
            'updatedat' => timenow(),
        ];

        Patient::query()->where('patient.id', $patientid)->update($data);

        // 保存日志
        $log = [
            'type'   => 'patient',
            'action' => 'hospitalized',
            'to'     => $data,
            'id'     => $patientid,
        ];
        $this->logRepository->record($log);
    }
}
