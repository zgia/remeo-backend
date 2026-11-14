<?php

namespace App\Service;

use App\Enums\ConstantType;
use App\Enums\VideoType;
use App\Exception\PatientException;
use App\Helper\ImageHelper;
use App\Models\Media;
use App\Models\Patient;
use App\Models\Progress;
use App\Models\Situation;
use App\Models\Treatment;
use App\Models\Video;
use App\Repositories\LogRepository;
use App\Repositories\PatientRepository;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Class PatientService
 */
class PatientService
{
    public function __construct(
        protected readonly PatientRepository $patientRepository,
        protected readonly LogRepository $logRepository,
        protected readonly SystemService $systemService,
    ) {}

    public string $videoFields = 'videoday,sequence,lesioncount,lesionposition,lesionpart,videostatus,videoresult,suggestion,sinus,ct,ctcalc,cemrilength,cemriwidth,cemrithick,t2flairlength,t2flairwidth,t2flairthick';

    public string $patientFields = '';

    public string $situationFields = '';

    public string $basicFields = 'checkday,checkway,checkalive,causeofdeath';

    /**
     * CT 相关
     * CT: 是否做过CT；CTCALC：CT有无钙化
     */
    public const CATEGORY_CT = [2 => '否', 3 => '本院', 4 => '外院'];

    public const CATEGORY_CTCALC = [1 => '是', 2 => '否'];

    /**
     * @var string 删除标记
     */
    public string $deleteTag = '';

    /**
     * 首页预览选择住院日期，hospseq：第几次住院，对应住院日期
     */
    public int $hospseqForHomepage = 0;

    /**
     * 患者生存(1)还是死亡(2)
     */
    public const PATIENT_IS_DEATH = '2';

    public const PATIENT_IS_ALIVE = '1';

    public const PATIENT_ALIVE_DEATH = [
        self::PATIENT_IS_ALIVE => '生存',
        self::PATIENT_IS_DEATH => '死亡',
    ];

    /**
     * 初始状态的复发(2)和初发(1)
     */
    public const INITIAL_STATE_RECURRENCE = '2';

    public const INITIAL_STATE_FIRT = '1';

    public const INITIAL_STATE_MAP = [
        self::INITIAL_STATE_FIRT       => '初发',
        self::INITIAL_STATE_RECURRENCE => '复发',
    ];

    /**
     * 影像记录
     *
     * 43未见复发   44复发  45病灶增大  46病灶稳定  47病灶变小
     * 44、45表示复发2，其他表示未复发
     */
    public const VIDEO_RESULT_RECURRENCE = 44;

    public const VIDEO_RESULT_BIGGER = 45;

    /**
     * 影像记录的复发(2)和未见复发(1)
     */
    public const VIDEO_RECURRENCE = '2';

    public const VIDEO_NOT_RECURRENCE = '1';

    public const VIDEO_STATE_MAP = [
        self::VIDEO_NOT_RECURRENCE => '初发',
        self::VIDEO_RECURRENCE     => '复发',
    ];

    /**
     * WHO分级未65，表示I级别，其他值（66,67）为II，III级
     */
    public const WHO_LEVEL_I = 65;

    /**
     * 手术方式，全切(1)，次全切(2)
     */
    public const SURGERY_WAY_CUT_ALL = 1;

    /**
     * @var int 当前操作的患者
     */
    public int $patientid = 0;

    /**
     * @var array<string,mixed> 当前操作的患者
     */
    public array $patient = [];

    public function getPatientid(): int
    {
        return $this->patientid;
    }

    public function setPatientid(int $patientid): void
    {
        $this->patientid = $patientid;
    }

    /**
     * Patient
     *
     * @return array<string,mixed>
     */
    public function getPatient(): array
    {
        return $this->patient;
    }

    /**
     * @param  array<string,mixed>  $patient
     */
    public function setPatient(array $patient): void
    {
        $this->patient = $patient;
    }

    /**
     * 获取患者信息
     *
     * @return array<string,mixed>
     */
    public function getPatientById(int $patientid)
    {
        $conds = [
            'patient.id'        => $patientid,
            'patient.deletedat' => 0,
        ];
        if (! isViewer(UserService::user())) {
            $conds['patient.hospitalid'] = currentHospitalId();
        }

        $patient = Patient::query()
            ->where($conds)
            ->select(explode(',', 'id AS patientid,isout,isin,'.$this->patientFields))
            ->first();
        $patient = $patient?->getAttributes();

        if (! $patient) {
            throw new PatientException('患者不存在(A10001)。', 404);
        }

        $this->setPatient($patient);
        $this->setPatientid($patientid);

        return $patient;
    }

    /**
     * 删除患者
     */
    public function deletePatient(): void
    {
        $patientid = $this->getPatientid();

        Patient::query()
            ->where('patient.id', $patientid)
            ->update([
                $this->deleteTag => 1,
                'deletedat'      => timenow(),
            ]);

        // 保存日志
        $log = [
            'type'   => 'patient',
            'action' => 'delete',
            'id'     => $patientid,
        ];
        $this->logRepository->record($log);
    }

    /**
     * 根据住院患者首页预览，选择的第几次住院，来确定获取当前住院的数据
     *
     * @param  array<string,mixed>  $patient
     * @return int
     */
    public function getCurrentHospseq(array $patient)
    {
        return $this->hospseqForHomepage ?: $patient['hospseq'];
    }

    /**
     * 获取门诊患者的复查/随访记录
     *
     * @return array<string,mixed>
     */
    public function getTreatment(int $treatmentid = 0): array
    {
        $patientid = $this->getPatientid();
        $patient   = $this->getPatient();

        // 门诊的首诊，复查或者住院随访
        if ($treatmentid) {
            $conds = [
                'id'        => $treatmentid,
                'patientid' => $patientid,
            ];
        } else {
            $conds = [
                'patientid' => $patientid,
                'outin'     => $this->getOutInType(),
                'hospseq'   => $patient['hospseq'],
            ];
        }

        $conds['deletedat'] = 0;

        // 不指定某次治疗的话，取最后一次的
        $treatment = Treatment::query()
            ->where($conds)
            ->select(explode(',', 'id AS treatmentid,outin,step,'.$this->basicFields))
            ->orderBy('id', 'desc')
            ->first();
        $treatment = $treatment?->getAttributes();

        if (! $treatment) {
            if ($treatmentid) {
                throw new PatientException('复查记录不存在。', 404);
            }

            return [];
        }

        $treatmentid = $treatment['treatmentid'];

        // 患者情况
        $situation = Situation::query()
            ->where('situation.treatmentid', $treatmentid)
            ->where('situation.deletedat', 0)
            ->select(explode(',', $this->situationFields))
            ->first();
        $situation = $situation?->getAttributes();

        // 影像学检查
        $video = Video::query()
            ->where('video.treatmentid', $treatmentid)
            ->where('video.deletedat', 0)
            ->select(explode(',', $this->videoFields))
            ->first();
        $video = $video?->getAttributes();

        return [
            'treatmentid' => $treatmentid,
            'patientid'   => $patientid,
            'patient'     => $patient,
            'treatment'   => $treatment,
            'situation'   => $situation,
            'video'       => $video,
        ];
    }

    /**
     * 患者的完整度记录
     */
    public function newProgress(int $patientid): void
    {

        $progress = Progress::query()->where('progress.patientid', $patientid)->exists();

        // 更新完整度
        if (! $progress) {
            Progress::query()->insert(['patientid' => $patientid]);
        }
    }

    /**
     * @see SituationType
     *
     * @return null|string
     */
    public function getSituationType()
    {
        return null;
    }

    /**
     * @see VideoType
     *
     * @return null|string
     */
    public function getVideoType()
    {
        return null;
    }

    /**
     * @see ConstantType
     *
     * @return null|string
     */
    public function getOutInType()
    {
        return null;
    }

    /**
     * 随访次数加1
     */
    public function addCheckCount(int $patientid): void {}

    /**
     * 更新下次复查时间
     *
     * @param  array<string,mixed>  $data
     */
    public function updateNextCheckDay(string $day, int $patientid, array &$data = []): void {}

    /**
     * 标记最后治疗的ID，用于数据导出功能
     */
    public function setLastCheckId(int $treatmentid, int $patientid): void {}

    /**
     * 还要做其他的事情
     *
     * @param  array<string,mixed>  $checkdata
     * @param  array<string,mixed>  $data
     */
    public function updateMoreChecks(array $checkdata, int $treatmentid, array &$data = []): void {}

    public static function getAliveOrDeath(?string $ad = null): string
    {
        if (! array_key_exists($ad, static::PATIENT_ALIVE_DEATH)) {
            $ad = static::PATIENT_IS_ALIVE;
        }

        return static::PATIENT_ALIVE_DEATH[$ad];
    }

    public static function getInitialStateTitle(?string $is = null): string
    {
        if (! array_key_exists($is, static::INITIAL_STATE_MAP)) {
            $is = static::INITIAL_STATE_FIRT;
        }

        return static::INITIAL_STATE_MAP[$is];
    }

    public static function getVideoStateTitle(?string $vs = null): string
    {
        if (! array_key_exists($vs, static::VIDEO_STATE_MAP)) {
            $vs = static::VIDEO_NOT_RECURRENCE;
        }

        return static::VIDEO_STATE_MAP[$vs];
    }

    /**
     * 准备数据
     *
     * @param  array<string,mixed>  $checkdata
     * @param  array<string,mixed>  $data
     * @return array<string,mixed>
     */
    public function prepairCheckData(array $checkdata, string $fields, bool $updated = true, array $data = []): array
    {
        $patient = $this->getPatient();

        if ($updated) {
            $data['updatedat'] = timenow();
        } else {
            $data['createdat'] = timenow();
            $data['patientid'] = $this->getPatientid();
            $data['hospseq']   = $patient['hospseq'];
        }

        foreach (explode(',', $fields) as $sf) {
            if (array_key_exists($sf, $checkdata)) {
                $data[$sf] = $checkdata[$sf];
            }
        }

        return $data;
    }

    /**
     * 复查，术后叫随访，一次随访，记录为一次治疗
     *
     * @return array<string,mixed>
     */
    public function updateCheck(array $checkdata)
    {
        $patientid = $this->getPatientid();

        $situation    = $checkdata['situation'];
        $video        = $checkdata['video'];
        $radiotherapy = $checkdata['radiotherapy'];
        $treatment    = $checkdata['treatment'];

        $treatmentid = $treatment['treatmentid'];
        unset($treatment['treatmentid']);

        $data = [];

        return DB::transaction(function () use ($data, $patientid, $radiotherapy, $situation, $treatment, $treatmentid, $video): array {
            if ($treatmentid) {
                // 基本情况写到treatment表
                Treatment::query()
                    ->where('treatment.id', $treatmentid)
                    ->where('treatment.patientid', $patientid)
                    ->update($this->prepairCheckData($treatment, $this->basicFields));

                // 患者情况
                Situation::query()
                    ->where('situation.treatmentid', $treatmentid)
                    ->where('situation.patientid', $patientid)
                    ->update($this->prepairCheckData($situation, $this->situationFields));

                // 影像学检查
                Video::query()
                    ->where('video.treatmentid', $treatmentid)
                    ->where('video.patientid', $patientid)
                    ->update($this->prepairCheckData($video, $this->videoFields));
            } else {
                // 检查当前患者第几次来就诊
                $step = (int) Treatment::query()
                    ->where('treatment.patientid', $patientid)
                    ->where('treatment.deletedat', 0)
                    ->max('step');

                // 基本情况写到treatment表
                $tbasic      = ['step' => $step + 1, 'outin' => $this->getOutInType()];
                $treatmentid = Treatment::query()
                    ->insertGetId($this->prepairCheckData($treatment, $this->basicFields, false, $tbasic));

                // 患者情况
                $sbase = [
                    'treatmentid' => $treatmentid,
                    'stype'       => $this->getSituationType(),
                ];
                Situation::query()->insert($this->prepairCheckData($situation, $this->situationFields, false, $sbase));

                // 影像学检查
                $vbase = [
                    'treatmentid' => $treatmentid,
                    'vtype'       => $this->getVideoType(),
                ];
                Video::query()->insert($this->prepairCheckData($video, $this->videoFields, false, $vbase));

                // 复查次数加1
                $this->addCheckCount($patientid);

                $this->setLastCheckId($treatmentid, $patientid);
            }

            $this->updateMoreChecks(['radiotherapy' => $radiotherapy, 'treatment' => $treatment, 'video' => $video], $treatmentid, $data);

            // 更新患者的最后复查时间和下次复查时间
            $this->updateNextCheckDay($treatment['checkday'], $patientid, $data);

            $data['treatmentid'] = $treatmentid;
            $data['patientid']   = $patientid;

            // 保存日志
            $log = [
                'type'   => 'check',
                'action' => $treatmentid ? 'update' : 'insert',
                'to'     => [$situation, $video, $treatment, $radiotherapy],
                'id'     => $patientid,
            ];
            $this->logRepository->record($log);

            return $data;
        });
    }

    /**
     * 为预览页面生成患者基本信息
     *
     * @param  array<string,mixed>  $patient
     */
    public function patientForPage(array &$patient): void
    {
        $patient['isfemale']         = isFemale($patient['gender']);
        $patient['gender']           = getGenderText($patient['gender']);
        $patient['isalive']          = static::getAliveOrDeath($patient['isalive'] ?? null);
        $patient['hospinitialstate'] = static::getInitialStateTitle($patient['hospinitialstate'] ?? null);
        $patient['recurrence']       = static::getVideoStateTitle($patient['recurrence'] ?? null);
    }

    /**
     * 为预览页面生成患者影像学检查信息
     *
     * @param  array<string,mixed>  $video
     * @param  array<string,array<string,mixed>>  $categories
     */
    public function videoForPage(array &$video, array $categories): void
    {
        if (empty($video)) {
            return;
        }

        $video['sequence']       = getYesNoText($video['sequence']);
        $video['lesionposition'] = $categories['lesionposition'][$video['lesionposition']] ?? '';

        $_lesionparts = [];
        foreach (explode(',', $video['lesionpart']) as $lp) {
            $_lesionparts[] = $categories['lesionpart'][$lp] ?? '';
        }
        $video['lesionpart'] = implode(', ', $_lesionparts);

        $video['sinus'] = getYesNoText($video['sinus']);
        if (! empty($video['videostatus'])) {
            $video['videostatus'] = $categories['videostatus'][$video['videostatus']] ?? '';
        }
        if (! empty($video['videoresult'])) {
            $video['videoresult'] = $categories['videoresult'][$video['videoresult']] ?? '';
        }
    }

    /**
     * 为预览页面生成患者当前情况
     *
     * @param  array<string,mixed>  $situation
     * @param  array<string,array<string,mixed>>  $categories
     */
    public function situationForPage(array &$situation, array $categories): void
    {
        $situation['kpsinfo']  = $categories['kps'][$situation['kps'] ?? 0] ?? '';
        $situation['epilepsy'] = getYesNoText($situation['epilepsy'] ?? null);
        if (! empty($situation['isfemale'])) {
            // 门诊和住院选择的字段不同，缺失或未填写的值不能推断为“是”。
            foreach (['uterectomy', 'uterinefibroids', 'menopause'] as $field) {
                $value             = (string) ($situation[$field] ?? '');
                $situation[$field] = in_array($value, ['1', '2']) ? getYesNoText($value) : '';
            }
        } else {
            $situation['uterectomy'] = '-';
            $situation['fertility']  = '-';

            $situation['uterinefibroids'] = '-';
            $situation['menopause']       = '-';
        }
    }

    /**
     * 入院情况
     *
     * @return array<string,mixed>
     */
    public function getSituation(int $treatmentid, string $stype)
    {
        $patientid = $this->getPatientid();
        $patient   = $this->getPatient();

        $conds = [
            'patientid' => $patientid,
            'stype'     => $stype,
            'hospseq'   => $this->getCurrentHospseq($patient),
            'deletedat' => 0,
        ];

        if ($treatmentid) {
            $conds['treatmentid'] = $treatmentid;
        }

        $situation = Situation::query()->where($conds)->select(explode(',', $this->situationFields))->first();
        $situation = $situation?->getAttributes();

        if (empty($situation)) {
            return [];
        }

        if (! empty($situation['fertility'])) {
            $fertility = [];

            [$fertility['g'],$fertility['p']] = array_pad(explode('-', $situation['fertility'], 2), 2, '');

            $situation['fertility'] = $fertility;
        }

        $situation['patientid'] = $patientid;

        return $situation;
    }

    /**
     * Clean
     *
     * @param  array<string,mixed>  $video
     */
    public function cleanVideoData(&$video): void
    {
        if (empty($video['videostatus'])) {
            $video['videostatus'] = '';
        }
        if (empty($video['lesioncount'])) {
            $video['lesioncount'] = '';
        }
        if (empty($video['lesionposition'])) {
            $video['lesionposition'] = '';
        }
        if (empty($video['lesionpart'])) {
            $video['lesionpart'] = '';
        }
        if (empty($video['sequence'])) {
            $video['sequence'] = '';
        }
        if (empty($video['sinus'])) {
            $video['sinus'] = '';
        }
        if (empty($video['ct'])) {
            $video['ct'] = '';
        }
        if (empty($video['ctcalc'])) {
            $video['ctcalc'] = '';
        }
        if (empty($video['videoresult'])) {
            $video['videoresult'] = '';
        }
    }

    /**
     * 计算影像中肿瘤的体积
     *
     * @param  array<string,mixed>  $video
     */
    public function calcVideoVol(array &$video): void
    {
        $cl = (int) $video['cemrilength'];
        $cw = (int) $video['cemriwidth'];
        $ct = (int) $video['cemrithick'];
        $tl = (int) $video['t2flairlength'];
        $tw = (int) $video['t2flairwidth'];
        $tt = (int) $video['t2flairthick'];

        $video['cemrivol']   = $cl * $cw * $ct                     / 2;
        $video['t2flairvol'] = $tl * $tw * $tt                     / 2;
        $video['edemavol']   = ($tl * $tw * $tt - $cl * $cw * $ct) / 2;
        // $video['edemavol'] = $video['edemavol'] < 0 ? 0 : $video['edemavol'];
    }

    /**
     * 影像学记录
     *
     * @return array<string,mixed>
     */
    public function getVideo(int $treatmentid, string $vtype)
    {
        $patientid = $this->getPatientid();
        $patient   = $this->getPatient();

        $conds = [
            'patientid' => $patientid,
            'vtype'     => $vtype,
            'hospseq'   => $this->getCurrentHospseq($patient),
            'deletedat' => 0,
        ];

        if ($treatmentid) {
            $conds['treatmentid'] = $treatmentid;
        }

        $video = Video::query()->where($conds)->select(explode(',', $this->videoFields))->first();
        $video = $video?->getAttributes();

        if (empty($video)) {
            return [];
        }

        $this->calcVideoVol($video);
        $this->cleanVideoData($video);

        return $video;
    }

    /**
     * 影像学记录
     *
     * 1. 住院患者的几个完整度不作为治疗过程，即不进Treatment表
     * 2. 门诊的首诊情况，影像学检查这2项作为作为第一次的治疗记录，这是因为编辑复查记录时，加载上次内容，包括首诊记录
     *
     * @param  array<string,mixed>  $video
     * @return array<string,mixed>
     */
    public function updateVideo(array $video)
    {
        $patientid = $this->getPatientid();
        $patient   = $this->getPatient();

        $old = Video::query()
            ->where('video.patientid', $patientid)
            ->where('video.vtype', $video['vtype'])
            ->where('video.hospseq', $patient['hospseq'])
            ->where('video.deletedat', 0)
            ->select('video.*')
            ->first();
        $old = $old?->getAttributes();

        return DB::transaction(function () use ($old, $patient, $patientid, $video): array {
            if ($old) {
                $video['updatedat'] = timenow();

                Video::query()->where('video.id', $old['id'])->update($video);
            } else {
                // 门诊的首诊情况，影像学检查这2项作为作为第一次的治疗记录，这是因为
                // 编辑复查记录时，加载上次内容，包括首诊记录
                if ($video['vtype'] == VideoType::OpFisrt->value) {
                    $this->addOpFirstVideoToTreatment($video);
                }

                $video['createdat'] = timenow();
                $video['hospseq']   = $patient['hospseq'];

                Video::query()->insert($video);
            }

            // 更新完整度
            $key = $video['vtype'] == VideoType::OpFisrt->value ? 'firstvideo' : 'vbs';
            Progress::query()->where('progress.patientid', $patientid)->update([$key => 1]);

            // 保存日志
            $log = [
                'type'   => 'video',
                'action' => $old ? 'update' : 'insert',
                'from'   => $old,
                'to'     => $video,
                'id'     => $patientid,
            ];
            $this->logRepository->record($log);

            return $video;
        });
    }

    /**
     * 门诊的首诊情况，影像学检查这2项作为作为第一次的治疗记录，这是因为编辑复查记录时，加载上次内容，包括首诊记录
     *
     * @param  array<string,mixed>  $video
     */
    public function addOpFirstVideoToTreatment(array &$video): void
    {
        $treatmentid = (int) Treatment::query()
            ->where('treatment.patientid', $video['patientid'])
            ->where('treatment.outin', ConstantType::HospitalizedOutpatient->value)
            ->where('treatment.step', 1)
            ->where('treatment.deletedat', 0)
            ->value('id');

        if ($treatmentid) {
            $video['treatmentid'] = $treatmentid;
        } else {
            $treatment = [
                'patientid' => $video['patientid'],
                'outin'     => ConstantType::HospitalizedOutpatient->value,
                'step'      => 1,
                'createdat' => timenow(),
            ];

            $video['treatmentid'] = Treatment::query()->insertGetId($treatment);
        }
    }

    /**
     * 患者复查、随访记录
     *
     * @return array<string,mixed>
     */
    public function checkForPage(int $treatmentid = 0)
    {
        $categories = $this->systemService->categoriesKV();
        $patient    = $this->getPatient();

        // 一、基本信息
        $this->patientForPage($patient);

        // 二、患者基本情况: 最后一次随访或者指定随访
        $conds = ['patientid' => $patient['patientid'], 'outin' => $this->getOutInType(), 'deletedat' => 0];
        if ($treatmentid) {
            $conds['id'] = $treatmentid;
        }
        $treatment = Treatment::query()
            ->where($conds)
            ->select('id', 'checkday', 'checkway', 'checkalive', 'causeofdeath')
            ->orderBy('id', 'desc')
            ->first();
        $treatment = $treatment?->getAttributes();
        if (empty($treatment)) {
            $ex = new PatientException('没有取到当前治疗记录。');
            $ex->setMore($conds);

            throw $ex;
        }

        $treatment['checkway']   = $categories['checkway'][$treatment['checkway']] ?? '';
        $treatment['checkalive'] = static::getAliveOrDeath($treatment['checkalive']);

        // 三、患者目前情况
        $situation             = $this->getSituation($treatment['id'], $this->getSituationType());
        $situation['isfemale'] = $patient['isfemale'];
        $this->situationForPage($situation, $categories);

        // 四、影像学检查
        $video = $this->getVideo($treatment['id'], $this->getVideoType());
        $this->videoForPage($video, $categories);

        $data = [
            'treatmentid' => $treatment['id'],
            'patientid'   => $patient['patientid'],

            'patient'   => $patient,
            'treatment' => $treatment,
            'situation' => $situation,
            'video'     => $video,
        ];

        return $data;
    }

    /**
     * 根据门诊/住院来分组统计患者数量
     *
     * @return array<int,int>
     */
    public function getTotalGroupbyType(): array
    {
        return $this->patientRepository->countByTypeForHospital(currentHospitalId());
    }

    /**
     * 通过 Laravel 文件存储保存影像，数据库写入失败时清理本次附件。
     *
     * @return array<string,mixed>
     */
    public function uploadMedia(UploadedFile $file): array
    {
        $disk      = Storage::disk('patient_media');
        $filename  = (string) Str::uuid();
        $extension = $file->extension();
        $path      = $file->storeAs((string) $this->patientid, $filename.'.'.$extension, 'patient_media');
        $thumbPath = $this->patientid.'/'.$filename.'-thumb.'.$extension;
        try {
            $thumb    = ImageHelper::thumb($disk->path($path), 150, 150, dirname($disk->path($path)).DIRECTORY_SEPARATOR);
            $url      = '/file/'.$path;
            $thumbUrl = $thumb ? '/file/'.$thumbPath : $url;
            $mediaId  = Media::query()->insertGetId([
                'patientid' => $this->patientid,
                'file'      => $url,
                'thumb'     => $thumbUrl,
                'mdesc'     => $file->getClientOriginalName(),
                'createdat' => timenow(),
            ]);
        } catch (\Throwable $exception) {
            $disk->delete([$path, $thumbPath]);
            throw $exception;
        }

        return ['id' => $mediaId, 'name' => $file->getClientOriginalName(), 'url' => $thumbUrl, 'fullurl' => $url];
    }

    /**
     * 影像资料
     *
     * @return array<array<string,mixed>>
     */
    public function getMedia()
    {
        $patientid = $this->getPatientid();

        $conds = [
            'patientid' => $patientid,
            'deletedat' => 0,
        ];

        $media = Media::query()
            ->where($conds)
            ->select('id', 'file', 'thumb', 'mdesc')
            ->get()
            ->keyBy('id')
            ->map(fn (Media $record): array => $record->getAttributes())
            ->all();

        $data = [];
        foreach ($media as $m) {
            $item = [
                'id'      => $m['id'],
                'url'     => $m['thumb'],
                'fullurl' => $m['file'],
                'uid'     => $m['id'],
                'name'    => $m['mdesc'],
            ];
            $data[] = $item;
        }

        return $data;
    }

    /**
     * 影像资料
     *
     * @return bool
     */
    public function deleteMedia(int $fileid)
    {
        $media = Media::query()->where('media.id', $fileid)->select('media.*')->first();
        $media = $media?->getAttributes();

        if ($media && $media['patientid'] == $this->getPatientid()) {
            Media::query()->where('media.id', $fileid)->update(['deletedat' => timenow()]);

            return true;
        }

        throw new PatientException('文件删除失败，请重试。');
    }

    /**
     * 导出为Word格式文件
     *
     * @return string 文件名
     */
    public function saveToWord(): string
    {
        throw new PatientException('当前服务未实现导出。');
    }

    /**
     * 导出为Excel格式文件
     *
     * @return string 文件名
     */
    public function saveToExcel(): string
    {
        throw new PatientException('当前服务未实现导出。');
    }

    public function download(): BinaryFileResponse
    {
        Gate::authorize('download-patient');
        $filename    = $this->getPatientid() ? $this->saveToWord() : $this->saveToExcel();
        $contentType = $this->getPatientid() ? 'application/vnd.ms-word' : 'application/vnd.ms-excel';

        return response()->download($filename, basename($filename), ['Content-Type' => $contentType])->deleteFileAfterSend(true);
    }

    public static function getAgeFromIdcard(string $idcard): int
    {
        if (empty($idcard) || strlen($idcard) != 18) {
            return 0;
        }

        $matches = [];
        preg_match('/\d{6}(\d{4})\d{7}[0-9Xx]/', $idcard, $matches);

        return (! empty($matches[1])) ? max(date('Y') - $matches[1], 0) : 0;
    }
}
