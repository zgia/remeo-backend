<?php

namespace App\Service;

use App\Enums\ConstantType;
use App\Enums\SituationType;
use App\Enums\SurgeryType;
use App\Enums\VideoType;
use App\Exception\PatientException;
use App\Helper\DateTimeHelper;
use App\Models\Hospday;
use App\Models\Hospital;
use App\Models\Pathology;
use App\Models\Patient;
use App\Models\Progress;
use App\Models\Radiotherapy;
use App\Models\Situation;
use App\Models\Surgery;
use App\Models\Treatment;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Class InhospitalService
 */
class InhospitalService extends PatientService
{
    public string $situationFields = 'kps,cc,positive,epilepsy,uterectomy,fertility,otherdesc';

    public string $patientFields = 'doctorid,hospitalid,age,gender,idcard,realname,mobile,linkman,linkrel,mobile2,linkman2,linkrel2,mobile3,linkman3,linkrel3,hospcode,hospday,hospseq,hospinitialstate,isalive,recurrence,nextfollowupday,lastfollowupday,followupcount';

    public string $radiotherapyFields = 'ryaction,rysyinterval,rycount,rydoses';

    public string $pathologyFields = 'pycode,pyday,histopathology,who,ki67,tertc228t,tertc250t,cdkn2a,cdkn2b,pten,h3k27m,pik3ca,smarcb1,pydesc';

    public string $surgeryFields = 'surgeryday,surgeryway,surgerydesc,bleedingvol,alloredcellsursp,alloplasma,tumorbloodsupply,tumortexture,tumoradhesionlevel,surgeryafter';

    /**
     * @var string 删除标记
     */
    public string $deleteTag = 'deletedin';

    /**
     * @see SituationType
     */
    public function getSituationType()
    {
        return SituationType::IhFollowup->value;
    }

    /**
     * @see VideoType
     */
    public function getVideoType()
    {
        return VideoType::IhFollowup->value;
    }

    /**
     * @see ConstantType
     */
    public function getOutInType()
    {
        return ConstantType::HospitalizedInhospital->value;
    }

    /**
     * 随访次数加1
     */
    public function addCheckCount(int $patientid): void
    {
        Patient::query()
            ->where('patient.id', $patientid)
            ->where('patient.hospitalid', currentHospitalId())
            ->increment('followupcount');
    }

    /**
     * 下次随访时间: 若组织病理的WHO分级为I级则为术后1年，若组织病理为2-3级则为术后半年
     * WHO分级未65，表示I级别，其他值（66,67）为II，III级
     *
     * @return int
     */
    public function getNextDayInterval(int $who)
    {
        return $who == static::WHO_LEVEL_I ? 12 : 6;
    }

    /**
     * 更新下次随访时间
     *
     * @param  array<string,mixed>  $data
     */
    public function updateNextCheckDay(string $day, int $patientid, array &$data = []): void
    {
        // @TODO
        // 下次随访时间: 若组织病理的WHO分级为I级则为术后1年，若组织病理为2-3级则为术后半年
        // WHO分级未65，表示I级别，其他值（66,67）为II，III级
        $who             = Pathology::query()->where('pathology.patientid', $patientid)->value('who');
        $nextfollowupday = DateTimeHelper::dayAddMonths($day, $this->getNextDayInterval((int) $who));

        $patient = [
            'lastfollowupday' => $day,
            'nextfollowupday' => $nextfollowupday,
            'updated_at'      => now(),
        ];

        Patient::query()->where('patient.id', $patientid)->where('patient.hospitalid', currentHospitalId())->update($patient);

        $data['lastfollowupday'] = $patient['lastfollowupday'];
        $data['nextfollowupday'] = $patient['nextfollowupday'];
    }

    /**
     * 门诊患者列表
     *
     * @return array<string,mixed>
     */
    public function items(string $realname, int $hospitalid = 0, string $sort = '', string $order = '')
    {
        $perpage = (int) getOption('perpage', 20);

        // 处理当前医院所有医生的住院患者
        $conds = ['patient.isin' => 1, 'patient.'.$this->deleteTag => 0];

        // -1表示来查看其他医院的患者
        if ($hospitalid == -1 && isViewer(UserService::user())) {
            $conds[] = ['patient.hospitalid', '!=', currentHospitalId()];
        } else {
            $conds['patient.hospitalid'] = currentHospitalId();
        }

        // 住院号、姓名搜索
        if ($realname) {
            // 住院号
            if (preg_match('/^[0-9a-zA-Z-_]+$/', $realname)) {
                $conds[] = ['patient.hospcode', 'LIKE', '%'.$realname.'%'];
            } else {
                $conds[] = ['patient.realname', 'LIKE', '%'.$realname.'%'];
            }
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
            ->select('patient.*', 'patient.id AS patientid', 'hospsituation', 'surgery', 'vbs', 'vas', 'pathology', 'discharge')
            ->orderBy($sort && $order ? 'patient.'.$sort : 'patient.id', $sort && $order ? $order : 'desc')
            ->forPage(currentPage(), $perpage)
            ->get()
            ->keyBy('id')
            ->map(fn (Patient $record): array => $record->getAttributes())
            ->all();

        $doctorids = array_unique(array_column($ps, 'doctorid'));
        $doctors   = User::query()
            ->withTrashed()
            ->whereIn('user.id', $doctorids)
            ->get()
            ->keyBy('id')
            ->all();

        $hospitalids = array_unique(array_column($ps, 'hospitalid'));
        $hospitals   = Hospital::query()
            ->withTrashed()
            ->whereIn('hospital.id', $hospitalids)
            ->get()
            ->keyBy('id')
            ->all();

        $patients = [];
        foreach ($ps as $p) {
            $hospital = $hospitals[$p['hospitalid']] ?? null;
            $doctor   = $doctors[$p['doctorid']]     ?? null;

            $patients[] = [
                'patientid'        => $p['patientid'],
                'hospital'         => $hospital ? ($hospital['title'].($hospital['deleted_at'] !== null ? '(已删除)' : '')) : '',
                'realname'         => $p['realname'],
                'age'              => static::getAgeFromIdcard($p['idcard']) ?: $p['age'],
                'hospcode'         => $p['hospcode'],
                'hospseq'          => $p['hospseq'],
                'hospday'          => $p['hospday'],
                'hospinitialstate' => static::getInitialStateTitle($p['hospinitialstate']),
                'progress'         => [
                    'hospsituation' => $p['hospsituation'] == yes(),
                    'surgery'       => $p['surgery']       == yes(),
                    'vbs'           => $p['vbs']           == yes(),
                    'vas'           => $p['vas']           == yes(),
                    'pathology'     => $p['pathology']     == yes(),
                    'discharge'     => $p['discharge']     == yes(),
                ],
                'recurrence'      => $p['recurrence'],
                'isalive'         => $p['isalive'],
                'followupcount'   => $p['followupcount'],
                'nextfollowupday' => $p['nextfollowupday'],
                'lastfollowupday' => $p['lastfollowupday'],
                'recorder'        => $doctor ? ($doctor['realname'].($doctor['deleted_at'] !== null ? '(已删除)' : '')) : '',
                'isfemale'        => isFemale($p['gender']),
            ];
        }

        return ['total' => $total, 'items' => $patients];
    }

    /**
     * 更新
     *
     * @param  array<string,mixed>  $patient
     * @return array<string,mixed>
     */
    public function addPatient(array $patient)
    {

        $hospcode = Patient::query()
            ->where('patient.hospitalid', currentHospitalId())
            ->where('patient.hospcode', $patient['hospcode'])
            ->count();
        if ($hospcode) {
            throw new PatientException('住院号（'.$patient['hospcode'].'）已经存在。');
        }

        $ps = Patient::query()
            ->where('patient.hospitalid', currentHospitalId())
            ->where('patient.realname', 'LIKE', $patient['realname'].'%')
            ->select('id', 'idcard', 'realname', 'mobile', 'mobile2', 'mobile3')
            ->get()
            ->keyBy('id')
            ->map(fn (Patient $record): array => $record->getAttributes())
            ->all();

        $newms = array_filter([$patient['mobile'], $patient['mobile2'], $patient['mobile3']]);

        $patientid = 0;
        foreach ($ps as $p) {
            if ($patient['idcard'] && $p['idcard'] == $patient['idcard']) {
                throw new PatientException('已经有身份证相同的患者（'.$p['realname'].'）了。');
            }

            $ms = [$p['mobile'], $p['mobile2'], $p['mobile3']];

            $same = array_intersect($newms, array_filter($ms));

            if ($same) {
                if ($p['isin'] == 1) {
                    throw new PatientException('已经有同名且联系方式一样的患者（'.$p['realname'].'）了。');
                }

                // 有相同患者，但是未住院，即门诊患者，则标记住院
                $patientid = $p['id'];

                break;
            }
        }

        // 通过2个参数：isout，isin来区分患者当前状态
        // 门诊，isout=1；isin=0
        // 住院，isout=0；isin=1
        // 门诊转住院，isout=1；isin=1
        $patient['isin'] = 1;

        return DB::transaction(function () use ($patient, $patientid): array {
            // 有相同患者，但是未住院，即门诊患者，则标记住院
            if ($patientid) {
                // 第一次住院
                $patient['hospseq']    = 1;
                $patient['updated_at'] = now();

                Patient::query()
                    ->where('patient.id', $patientid)
                    ->where('patient.hospitalid', currentHospitalId())
                    ->update($patient);

                $patient['patientid'] = $patientid;
            } else {
                // 第一次住院
                $patient['hospseq']    = 1;
                $patient['doctorid']   = currentUserId();
                $patient['hospitalid'] = currentHospitalId();
                $patient['created_at'] = now();
                $patient['updated_at'] = now();

                $patient['patientid'] = Patient::query()->insertGetId($patient);
            }

            // 完整度检查
            $this->newProgress($patient['patientid']);

            // 保存日志
            $log = [
                'type'   => 'patient',
                'action' => $patientid ? 'change' : 'insert',
                'to'     => $patient,
                'id'     => $patientid,
            ];
            $this->logRepository->record($log);

            return $patient;
        });
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

        // 当前医院内是否有相同的住院号
        $hospcode = Patient::query()
            ->where('patient.hospitalid', currentHospitalId())
            ->where('patient.hospcode', $patient['hospcode'])
            ->where('patient.id', '!=', $patientid)
            ->count();
        if ($hospcode) {
            throw new PatientException('住院号（'.$patient['hospcode'].'）已经存在。');
        }

        // 是否有相同的身份证
        if ($patient['idcard']) {
            $p = Patient::query()
                ->where('patient.hospitalid', currentHospitalId())
                ->where('patient.idcard', $patient['idcard'])
                ->where('patient.id', '!=', $patientid)
                ->select('realname')
                ->first();
            $p = $p?->getAttributes();

            if ($p) {
                throw new PatientException('已经有身份证相同的患者（'.$p['realname'].'）了。');
            }
        }

        $patient['updated_at'] = now();

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
     * 患者再次住院
     *
     * 关于二次住院或者多次住院，设计思路：
     *
     *  1）完整度中，“出院”有记录后，在个人信息编辑的弹窗的左下角，有个按钮：再次住院
     *  2）点击“再次住院”后，几个区域的变化如下：
     *
     *  2.1）住院号增加一个标志，比如422321-②，422321-③，表示当前第几次住院
     *  2.2）初始状态保持不变
     *  2.3）完整度清空，即6个按钮变灰，可以再次添加信息
     *  2.4）生存、复发，按照新的完整度信息来相应变化
     *  2.5）随访次数不变
     *  2.6）随访时间根据完整度来设置
     *  2.7）首页预览，可以选择第几次住院的预览，默认显示当前住院的
     *  2.8）随访记录预览，可以选择所有的随访时间
     *
     * @return int 第几次住院
     */
    public function inhospAgain()
    {
        $patientid = $this->getPatientid();
        $patient   = $this->getPatient();

        $seq = (int) $patient['hospseq'] + 1;

        return DB::transaction(function () use ($patient, $patientid, $seq): int {
            // 2.1）住院号增加一个标志，比如422321-②，422321-③，表示当前第几次住院
            // 通过hospseq（1，2，3， 4...）来标记第几次住院
            Patient::query()->where('patient.id', $patientid)->update([
                'hospday'         => null,
                'hospseq'         => $seq,
                'nextfollowupday' => null,
                'lastfollowupday' => null,
            ]);

            // 记录上次住院时间
            Hospday::query()
                ->insert([
                    'patientid'  => $patientid,
                    'hospseq'    => (int) $patient['hospseq'],
                    'inday'      => $patient['hospday'],
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

            // 2.3）完整度清空，即6个按钮变灰，可以再次添加信息
            Progress::query()->where('progress.patientid', $patientid)->update([
                'hospsituation' => 0,
                'surgery'       => 0,
                'vbs'           => 0,
                'vas'           => 0,
                'pathology'     => 0,
                'discharge'     => 0,
            ]);

            // 保存日志
            $log = [
                'type'   => 'patient',
                'action' => 'inhospagain',
                'to'     => ['seq' => $seq],
                'id'     => $patientid,
            ];
            $this->logRepository->record($log);

            return $seq;
        });
    }

    /**
     * 更新住院码
     *
     * @param  array<string,mixed>  $patient
     */
    public function updateCode(array $patient): void
    {
        $patientid = $this->getPatientid();

        $hospcode = Patient::query()
            ->where('patient.hospcode', $patient['hospcode'])
            ->where('patient.hospitalid', currentHospitalId())
            ->count();
        if ($hospcode) {
            throw new PatientException('住院号（'.$hospcode.'）已经存在。');
        }

        Patient::query()->where('patient.id', $patientid)->where('patient.hospitalid', currentHospitalId())->update($patient);

        // 保存日志
        $log = [
            'type'   => 'patient',
            'action' => 'hospcode',
            'to'     => $patient,
            'id'     => $patientid,
        ];
        $this->logRepository->record($log);
    }

    /**
     * 更新初始状态，存在手术表(surgery)
     *
     * @param  array<string,mixed>  $data
     */
    public function updateInitialState(array $data): void
    {
        $patientid = $this->getPatientid();
        $patient   = $this->getPatient();

        DB::transaction(function () use ($data, $patient, $patientid): void {
            $initialstate = $data['initialstate'];
            unset($data['initialstate']);

            // 初始状态：1：初发，2：复发
            Patient::query()
                ->where('patient.id', $patientid)
                ->where('patient.hospitalid', currentHospitalId())
                ->update(['hospinitialstate' => $initialstate]);

            if ($initialstate == static::INITIAL_STATE_RECURRENCE) {
                foreach ($data as $step => $sy) {
                    // 不能全部为空
                    if (! ($sy['surgeryday'] || $sy['surgeryway'] || $sy['tumorbloodsupply'] || $sy['tumortexture'] || $sy['tumoradhesionlevel'])) {
                        continue;
                    }
                    $sy['bleedingvol']      = (int) $sy['bleedingvol'];
                    $sy['alloredcellsursp'] = (int) $sy['alloredcellsursp'];
                    $sy['alloplasma']       = (int) $sy['alloplasma'];

                    unset($sy['label']);

                    $sytype = SurgeryType::getValue($step);
                    $old    = $this->getSurgery($sytype);

                    if ($old) {
                        $sy['updated_at'] = now();
                        $sy['patientid']  = $patientid;
                        Surgery::query()->where('surgery.id', $old['id'])->update($sy);
                    } else {
                        $sy['sytype']     = $sytype;
                        $sy['patientid']  = $patientid;
                        $sy['hospseq']    = $patient['hospseq'];
                        $sy['created_at'] = now();
                        $sy['updated_at'] = now();

                        Surgery::query()->insert($sy);
                    }
                }
            } else {
                // 修改成初发时，移除复发时的记录
                $types = SurgeryType::getInitialStateTypes();

                Surgery::query()
                    ->where('surgery.patientid', $patientid)
                    ->whereIn('surgery.sytype', array_keys($types))
                    ->delete();
            }

            // 保存日志
            $log = [
                'type'   => 'initialstate',
                'action' => 'update',
                'to'     => $data,
                'id'     => $patientid,
            ];
            $this->logRepository->record($log);

        });
    }

    /**
     * 患者初始状态
     *
     * @return array<string,mixed>
     */
    public function getInitialState()
    {
        $patient = $this->getPatient();

        $types = SurgeryType::getInitialStateTypes();

        $items = Surgery::query()
            ->where('surgery.patientid', $patient['patientid'])
            ->whereIn('surgery.sytype', array_keys($types))
            ->where('surgery.hospseq', $patient['hospseq'])
            ->select(explode(',', 'sytype,'.$this->surgeryFields))
            ->get()
            ->map(fn (Surgery $record): array => $record->getAttributes())
            ->all();

        $states = [];
        foreach ($items as $item) {
            $type = $item['sytype'];
            unset($item['sytype'],$item['surgerydesc'],$item['surgeryafter']);

            if ($type == SurgeryType::Initial->value) {
                $states['initialstate'] = $patient['hospinitialstate'];
            }

            $states[$types[$type]] = $item;
        }

        if (empty($states) && $patient['hospinitialstate']) {
            $states['initialstate'] = $patient['hospinitialstate'];
        }

        return $states;
    }

    /**
     * 入院情况
     *
     * 住院患者的几个完整度不作为治疗过程，即不进Treatment表
     *
     * @param  array<string,mixed>  $situation
     * @return array<string,mixed>
     */
    public function updateSituation(array $situation)
    {
        $patientid = $this->getPatientid();
        $patient   = $this->getPatient();

        // 是否入院情况
        $stype = $situation['comefrom'];
        unset($situation['comefrom']);

        $old = Situation::query()
            ->where('situation.patientid', $patientid)
            ->where('situation.stype', $stype)
            ->where('situation.hospseq', $patient['hospseq'])
            ->select('situation.*')
            ->first();
        $old = $old?->getAttributes();

        if (! empty($situation['fertility'])) {
            $situation['fertility'] = $situation['fertility']['g'].'-'.$situation['fertility']['p'];
        }

        if (empty($situation['fertility'])) {
            $situation['fertility'] = '';
        }

        return DB::transaction(function () use ($old, $patient, $patientid, $situation, $stype): array {
            if ($old) {
                $situation['updated_at'] = now();
                Situation::query()->where('situation.id', $old['id'])->update($situation);
            } else {
                $situation['stype']      = $stype;
                $situation['created_at'] = now();
                $situation['updated_at'] = now();
                $situation['hospseq']    = $patient['hospseq'];

                Situation::query()->insert($situation);
            }

            // 更新完整度
            $key = $stype == SituationType::IhInhospital->value ? 'hospsituation' : 'discharge';
            Progress::query()->where('progress.patientid', $patientid)->update([$key => 1]);

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
     * 影像学记录
     *
     * 住院患者的几个完整度不作为治疗过程，即不进Treatment表
     *
     * @param  array<string,mixed>  $surgery
     * @return array<string,mixed>
     */
    public function updateSurgery(array $surgery)
    {
        $patientid = $this->getPatientid();
        $patient   = $this->getPatient();

        $old = $this->getSurgery();

        return DB::transaction(function () use ($old, $patient, $patientid, $surgery): array {
            if ($old) {
                $surgery['updated_at'] = now();

                Surgery::query()->where('surgery.id', $old['id'])->update($surgery);
            } else {
                $surgery['created_at'] = now();
                $surgery['updated_at'] = now();
                $surgery['hospseq']    = $patient['hospseq'];

                Surgery::query()->insert($surgery);

                // 记录手术序号，即最后一次手术是第几次住院时的手术
                Patient::query()->where('patient.id', $patientid)->update(['surgeryseq' => $patient['hospseq']]);
            }

            // 更新完整度
            Progress::query()->where('progress.patientid', $patientid)->update(['surgery' => 1]);

            // 保存日志
            $log = [
                'type'   => 'surgery',
                'action' => $old ? 'update' : 'insert',
                'from'   => $old,
                'to'     => $surgery,
                'id'     => $patientid,
            ];
            $this->logRepository->record($log);

            return $surgery;
        });
    }

    /**
     * 术后特殊情况
     *
     * 住院患者的几个完整度不作为治疗过程，即不进Treatment表
     *
     * @param  array<string,mixed>  $surgery
     * @return array<string,mixed>
     */
    public function updateSurgeryAfter(array $surgery)
    {
        $patientid = $this->getPatientid();
        $patient   = $this->getPatient();

        $old = $this->getSurgery();
        if (empty($old)) {
            throw new PatientException('术后特殊情况应该在手术之后。');
        }

        return DB::transaction(function () use ($old, $patientid, $surgery): array {
            $data = ['surgeryafter' => $surgery['surgeryafter'], 'updated_at' => now()];
            Surgery::query()->where('surgery.id', $old['id'])->update($data);

            // 更新完整度
            Progress::query()->where('progress.patientid', $patientid)->update(['vas' => 1]);

            // 保存日志
            $log = [
                'type'   => 'surgeryafter',
                'action' => 'update',
                'from'   => $old,
                'to'     => $surgery,
                'id'     => $patientid,
            ];
            $this->logRepository->record($log);

            return $surgery;
        });
    }

    /**
     * 手术记录
     *
     * @return array<string,mixed>
     */
    public function getSurgery(?string $sytype = null)
    {
        $patient           = $this->getPatient();
        $sytype || $sytype = SurgeryType::Surgery->value;

        $item = Surgery::query()
            ->where('surgery.patientid', $patient['patientid'])
            ->where('surgery.sytype', $sytype)
            ->where('surgery.hospseq', $this->getCurrentHospseq($patient))
            ->select(explode(',', 'id,'.$this->surgeryFields))
            ->first();
        $item = $item?->getAttributes();

        return $item ?? [];
    }

    /**
     * 为预览页面生成患者手术信息
     *
     * @param  array<string,mixed>  $surgery
     */
    public function surgeryForPage(&$surgery): void
    {
        $surgery['surgeryway'] = $surgery['surgeryway'] ? ($surgery['surgeryway'] == static::SURGERY_WAY_CUT_ALL ? '全切' : '次全切') : '';
    }

    /**
     * 病理记录
     *
     * 住院患者的几个完整度不作为治疗过程，即不进Treatment表
     *
     * @param  array<string,mixed>  $pathology
     * @return array<string,mixed>
     */
    public function updatePathology(array $pathology)
    {
        $patientid = $this->getPatientid();
        $patient   = $this->getPatient();

        $surgery = $this->getSurgery();
        if (empty($surgery)) {
            throw new PatientException('病理应该在手术之后。');
        }

        $old = Pathology::query()
            ->where('pathology.patientid', $patientid)
            ->where('pathology.hospseq', $patient['hospseq'])
            ->select('pathology.*')
            ->first();
        $old = $old?->getAttributes();

        return DB::transaction(function () use ($old, $pathology, $patient, $patientid, $surgery): array {
            if ($old) {
                $pathology['updated_at'] = now();
                Pathology::query()->where('pathology.id', $old['id'])->update($pathology);
            } else {
                $pathology['created_at'] = now();
                $pathology['updated_at'] = now();
                $pathology['hospseq']    = $patient['hospseq'];

                Pathology::query()->insert($pathology);
            }

            // 下次随访时间: 若组织病理的WHO分级为I级则为术后1年，若组织病理为2-3级则为术后半年
            // WHO分级未65，表示I级别，其他值（66,67）为II，III级
            $nextfollowupday = DateTimeHelper::dayAddMonths($surgery['surgeryday'], $this->getNextDayInterval($pathology['who']));
            Patient::query()
                ->where('patient.id', $patientid)
                ->update(['nextfollowupday' => $nextfollowupday, 'updated_at' => now()]);

            $pathology['nextfollowupday'] = $nextfollowupday;

            // 更新完整度
            Progress::query()->where('progress.patientid', $patientid)->update(['pathology' => 1]);

            // 保存日志
            $log = [
                'type'   => 'pathology',
                'action' => $old ? 'update' : 'inseert',
                'from'   => $old,
                'to'     => $pathology,
                'id'     => $patientid,
            ];
            $this->logRepository->record($log);

            return $pathology;
        });
    }

    /**
     * 病理记录
     *
     * @return array<string,mixed>
     */
    public function getPathology()
    {
        $patientid = $this->getPatientid();
        $patient   = $this->getPatient();

        $item = Pathology::query()
            ->where('pathology.patientid', $patientid)
            ->where('pathology.hospseq', $this->getCurrentHospseq($patient))
            ->select(explode(',', $this->pathologyFields))
            ->first();
        $item = $item?->getAttributes();

        return $item ?? [];
    }

    /**
     * 为预览页面生成患者病理信息
     *
     * @param  array<string,mixed>  $pathology
     * @param  array<string,mixed>  $categories
     */
    public function pathologyForPage(&$pathology, $categories): void
    {
        $pathology['histopathology'] = $categories['histopathology'][$pathology['histopathology']] ?? '';
        $pathology['who']            = $categories['who'][$pathology['who']]                       ?? '';
        $pathology['cdkn2a']         = $categories['cdkn2'][$pathology['cdkn2a']]                  ?? '';
        $pathology['cdkn2b']         = $categories['cdkn2'][$pathology['cdkn2b']]                  ?? '';
        $pathology['ki67']           = $pathology['ki67'].'%';
        $pathology['tertc228t']      = $categories['wtmut'][$pathology['tertc228t']] ?? '';
        $pathology['tertc250t']      = $categories['wtmut'][$pathology['tertc250t']] ?? '';
        $pathology['pten']           = $categories['wtmut'][$pathology['pten']]      ?? '';
        $pathology['h3k27m']         = $categories['wtmut'][$pathology['h3k27m']]    ?? '';
        $pathology['pik3ca']         = $categories['wtmut'][$pathology['pik3ca']]    ?? '';
        $pathology['smarcb1']        = $categories['wtmut'][$pathology['smarcb1']]   ?? '';
    }

    /**
     * 随访：基本情况，患者情况，影像学检查，放疗
     *
     * @return array<string,mixed>
     */
    public function updateFollowup(array $checkdata)
    {
        return $this->updateCheck($checkdata);
    }

    /**
     * 标记最后治疗的ID，用于数据导出功能
     */
    public function setLastCheckId(int $treatmentid, int $patientid): void
    {
        Patient::query()->where('patient.id', $patientid)->update(['lastfollowupid' => $treatmentid]);
    }

    /**
     * 还要做其他的事情
     *
     * @param  array<string,mixed>  $checkdata
     * @param  array<string,mixed>  $data
     */
    public function updateMoreChecks(array $checkdata, int $treatmentid, array &$data = []): void
    {
        // 放疗
        $this->updateRadiotherapy($checkdata['radiotherapy'], $treatmentid);

        // 根据患者情况设置生存死亡、是否复发等等
        // 43未见复发   44复发  45病灶增大  46病灶稳定  47病灶变小
        // 44、45表示复发2，其他表示未复发
        $patient = [
            'isalive'    => $checkdata['treatment']['checkalive'],
            'recurrence' => ($checkdata['video']['videoresult'] == static::VIDEO_RESULT_RECURRENCE || $checkdata['video']['videoresult'] == static::VIDEO_RESULT_BIGGER) ? static::VIDEO_RECURRENCE : static::VIDEO_NOT_RECURRENCE,
        ];
        Patient::query()->where('patient.id', $this->getPatientid())->update($patient);

        $data['isalive']    = $patient['isalive'];
        $data['recurrence'] = $patient['recurrence'];
    }

    /**
     * 更新放疗
     *
     * @param  array<string,mixed>  $radiotherapy
     */
    public function updateRadiotherapy(array $radiotherapy, int $treatmentid): void
    {

        if ($radiotherapy['treatmentid']) {
            Radiotherapy::query()
                ->where('radiotherapy.treatmentid', $radiotherapy['treatmentid'])
                ->where('radiotherapy.patientid', $this->getPatientid())
                ->update($this->prepairCheckData($radiotherapy, $this->radiotherapyFields));
        } else {
            $r = ['treatmentid' => $treatmentid];
            Radiotherapy::query()->insert($this->prepairCheckData($radiotherapy, $this->radiotherapyFields, false, $r));
        }

        // 保存日志
        $log = [
            'type'   => 'radiotherapy',
            'action' => $radiotherapy['treatmentid'] ? 'update' : 'inseert',
            'to'     => $radiotherapy,
            'id'     => $this->getPatientid(),
        ];
        $this->logRepository->record($log);
    }

    /**
     * 放疗
     *
     * @return array<string,mixed>
     */
    public function getRadiotherapy(int $treatmentid)
    {
        $patient = $this->getPatient();

        $radiotherapy = Radiotherapy::query()
            ->where('radiotherapy.treatmentid', $treatmentid)
            ->where('radiotherapy.patientid', $patient['patientid'])
            ->where('radiotherapy.hospseq', $patient['hospseq'])
            ->select(explode(',', 'treatmentid,patientid,'.$this->radiotherapyFields))
            ->first();
        $radiotherapy                  = $radiotherapy?->getAttributes();
        $radiotherapy || $radiotherapy = [];

        return $radiotherapy;
    }

    /**
     * 随访：基本情况，患者情况，影像学检查，放疗
     *
     * @return array<string,mixed>
     */
    public function getFollowup(int $treatmentid = 0)
    {
        $data = parent::getTreatment($treatmentid);
        if (! $data) {
            return [];
        }

        // 添加放疗
        $data['radiotherapy'] = $this->getRadiotherapy($data['treatmentid']);

        $followup = ['treatmentid' => $data['treatmentid'], 'patientid' => $data['patientid']];

        foreach (['treatment', 'situation', 'video', 'radiotherapy'] as $key) {
            if (array_key_exists($key, $data)) {
                $followup[$key] = $data[$key];
            }
        }

        return $followup;
    }

    /**
     * 随访次数
     *
     * @return int
     */
    public function getFollowupCount()
    {
        $patientid = $this->getPatientid();

        return Treatment::query()
            ->where('treatment.patientid', $patientid)
            ->where('treatment.outin', $this->getOutInType())
            ->count();
    }

    /**
     * 患者首页记录
     *
     * 注：首页记录中的数据，不进治疗表，因此getSituation等方法的第一个参数treatmentid为0
     *
     * @return array<string,mixed>
     */
    public function getHomepage(int $hospseq = 0)
    {
        $categories = $this->systemService->categoriesKV();

        // 第几次住院和对应的住院日期
        $hospdays = array_column($this->getHomePageDayList(), 'hospday', 'hospseq');

        $patient = $this->getPatient();

        // 一、基本信息，含什么时候住院的
        if ($hospseq) {
            if (! array_key_exists($hospseq, $hospdays)) {
                throw new PatientException('住院记录不存在。');
            }

            $patient['hospday'] = $hospdays[$hospseq];
        }

        // 第几次住院？
        $this->hospseqForHomepage = $hospseq;

        $patientid = $patient['patientid'];
        $this->patientForPage($patient);

        $data = ['patientid' => $patientid, 'patient' => $patient];

        // 二、入院情况
        $situation = $this->getSituation(0, SituationType::IhInhospital->value);
        if ($situation) {
            $situation['isfemale'] = $patient['isfemale'];
            $this->situationForPage($situation, $categories);
        }
        $data['situation'] = $situation;

        // 三、术前检查
        $before = $this->getVideo(0, VideoType::IhBefore->value);
        $this->videoForPage($before, $categories);

        $data['before'] = $before;

        // 四、手术
        $surgery = $this->getSurgery();
        if ($surgery) {
            $surgery['surgeryway'] = $surgery['surgeryway'] == static::SURGERY_WAY_CUT_ALL ? '全切' : '次全切';
        }
        $data['surgery'] = $surgery;

        // 五、术后检查
        // 已经使用 surgery.surgeryafter 代替

        // 六、病理
        $pathology = $this->getPathology();
        if ($pathology) {
            $this->pathologyForPage($pathology, $categories);
        }
        $data['pathology'] = $pathology;

        // 七、出院
        $discharge = $this->getSituation(0, SituationType::IhDischarge->value);
        if ($discharge) {
            $discharge['kpsinfo'] = $categories['kps'][$discharge['kps']] ?? '';
        }
        $data['discharge'] = $discharge;

        return $data;
    }

    /**
     * 获取患者的住院日期，提供给前台筛选
     *
     * @return list<array<string,mixed>>
     */
    public function getHomePageDayList()
    {
        $patientid = $this->getPatientid();
        $patient   = $this->getPatient();

        $days = Hospday::query()
            ->where('hospday.patientid', $patientid)
            ->select('hospseq', 'inday AS hospday')
            ->get()
            ->map(fn (Hospday $record): array => $record->getAttributes())
            ->all();

        $days[] = ['hospseq' => $patient['hospseq'], 'hospday' => $patient['hospday'] ?: '未填入住院日期'];

        return $days;
    }

    /**
     * 患者随访记录
     *
     * @return array<string,mixed>
     */
    public function getFollowuppage(int $treatmentid)
    {
        $data = $this->checkForPage($treatmentid);
        if (! $data) {
            return [];
        }

        // 五、放疗
        $radiotherapy = $this->getRadiotherapy($data['treatmentid']);
        if ($radiotherapy) {
            $radiotherapy['ryaction'] = getYesNoText($radiotherapy['ryaction']);
        }

        $data['radiotherapy'] = $radiotherapy;

        return $data;
    }

    /**
     * 获取患者的随访日期，提供给前台筛选
     *
     * @return list<array<string,mixed>>
     */
    public function getFollowupDayList()
    {
        $patientid = $this->getPatientid();

        return Treatment::query()
            ->where('treatment.patientid', $patientid)
            ->where('treatment.outin', $this->getOutInType())
            ->select('id AS treatmentid', 'checkday')
            ->get()
            ->map(fn (Treatment $record): array => $record->getAttributes())
            ->all();
    }
}
