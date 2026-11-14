<?php

namespace App\Service;

use App\Enums\SituationType;
use App\Enums\SurgeryType;
use App\Enums\VideoType;
use App\Models\Hospital;
use App\Models\Pathology;
use App\Models\Patient;
use App\Models\Radiotherapy;
use App\Models\Situation;
use App\Models\Surgery;
use App\Models\Treatment;
use App\Models\Video;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpWord\TemplateProcessor;

/**
 * Class ExportInhospitalService
 */
class ExportInhospitalService extends InhospitalService
{
    public const INHOSPITAL_FIELDS = [
        '基本信息' => [
            'realname'         => '姓名',
            'gender'           => '性别',
            'age'              => '年龄',
            'hospcode'         => '住院号',
            'hospday'          => '住院日期',
            'mobile'           => '联系方式',
            'idcard'           => '身份证',
            'hospinitialstate' => '初始状态',
            'isalive'          => '生存情况',
            'recurrence'       => '复发与否',
            'followupcount'    => '随访次数',
            'lastfollowupday'  => '末次随访日期',
        ],
        '入院情况' => [
            'kps'        => 'KPS',
            'cc'         => '主诉',
            'positive'   => '阳性体征',
            'epilepsy'   => '是否有癫痫发作',
            'uterectomy' => '是否子宫切除',
            'fertility'  => '生育情况',
            'otherdesc'  => '其他',
        ],
        '术前影像' => [
            'videoday'      => '日期',
            'sequence'      => '序列是否符合要求',
            'lesioncount'   => '病灶数量',
            'lesionpart'    => '病灶部位',
            'sinus'         => '是否累及窦',
            'cemrilength'   => '长',
            'cemriwidth'    => '宽',
            'cemrithick'    => '高',
            'cemrivol'      => 'T1+C体积',
            't2flairlength' => '长',
            't2flairwidth'  => '宽',
            't2flairthick'  => '高',
            't2flairvol'    => 'T2/FLAIR体积',
            'edemavol'      => '水肿体积',
        ],
        '手术情况' => [
            'surgeryday'         => '手术日期',
            'surgeryway'         => '手术方式',
            'surgerydesc'        => '手术描述',
            'bleedingvol'        => '术中出血',
            'alloredcellsursp'   => '异体悬浮红细胞',
            'alloplasma'         => '异体血浆',
            'tumorbloodsupply'   => '肿瘤血供',
            'tumortexture'       => '肿瘤质地',
            'tumoradhesionlevel' => '粘连程度',
            'surgeryafter'       => '术后特殊情况',
        ],
        '病理' => [
            'pycode'         => '病理号',
            'histopathology' => '组织病理',
            'who'            => 'WHO分级',
            'ki67'           => 'Ki-67',
            'tertc228t'      => 'TERT C228T',
            'tertc250t'      => 'TERT C250T',
            'cdkn2a'         => 'CDKN2A纯合性缺失',
            'cdkn2b'         => 'CDKN2B纯合性缺失',
            'pten'           => 'PTEN',
            'h3k27m'         => 'H3K27M',
            'pik3ca'         => 'PIK3CA',
            'smarcb1'        => 'SMARCB1',
            'pydesc'         => '备注'],
        '出院' => [
            'kps'       => 'KPS',
            'cc'        => '主诉',
            'positive'  => '阳性体征',
            'otherdesc' => '其他特殊情况',
        ],
        '随访-基本情况' => [
            'checkday'   => '复查日期',
            'checkway'   => '复查方式',
            'checkalive' => '是否存活',
        ],
        '随访-患者情况' => [
            'kps'      => 'KPS',
            'cc'       => '主诉',
            'positive' => '阳性体征',
            'epilepsy' => '术后是否癫痫发作',
        ],
        '随访-影像学检查' => [
            'videoday'      => '影像日期',
            'videostatus'   => '本次检查',
            'sequence'      => '序列是否符合要求',
            'lesioncount'   => '病灶数量',
            'lesionpart'    => '病灶部位',
            'sinus'         => '是否累及窦',
            'cemrilength'   => '长',
            'cemriwidth'    => '宽',
            'cemrithick'    => '高',
            'cemrivol'      => 'T1+C体积',
            't2flairlength' => '长',
            't2flairwidth'  => '宽',
            't2flairthick'  => '高',
            't2flairvol'    => 'T2/FLAIR体积',
            'edemavol'      => '水肿体积',
            'videoresult'   => '影像评估结果',
        ],
        '随访-放疗' => [
            'ryaction'     => '是否放疗',
            'rysyinterval' => '手术与放疗间隔',
            'rycount'      => '放疗次数',
            'rydoses'      => '每次放疗剂量',
        ],
    ];

    /**
     * 什么记录可以导出
     *
     * 1，有手术记录的，导出第一次手术的住院记录
     * 2，没有手术记录的，导出最后一次住院记录
     *
     * @param  array<string,mixed>  $patient
     * @return bool
     */
    public function ihBeExported(?array $patient = null, int $seq = 0)
    {
        return $seq == ($patient['surgeryseq'] ?? ($patient['hospseq'] ?? 0));
    }

    /**
     * 入院前的基本情况
     *
     * @param  array<string,mixed>  $patients
     * @param  array<string,mixed>  $categories
     * @return array<string,mixed>
     */
    private function basic(array $patients, array $categories)
    {
        $conds = [
            'stype'               => SituationType::IhInhospital->value,
            'situation.deletedat' => 0,
        ];
        $data = Situation::query()
            ->where($conds)
            ->join('patient AS p', 'p.id', '=', 'situation.patientid')
            ->where('p.deletedat', 0)
            ->select(explode(',', 'situation.id,patientid,situation.hospseq,'.$this->situationFields))
            ->get()
            ->keyBy('id')
            ->map(fn (Situation $record): array => $record->getAttributes())
            ->all();
        foreach ($data as $id => &$v) {
            if (empty($patients[$v['patientid']]) || ! $this->ihBeExported($patients[$v['patientid']], $v['hospseq'])) {
                unset($data[$id]);

                continue;
            }
            unset($v['id'], $v['hospseq']);

            $v['isfemale'] = isFemale($patients[$v['patientid']]['gender']);

            if (! empty($v['fertility'])) {
                $v['fertility'] = preg_replace('/(\d+)-(\d+)/', 'G$1P$2', $v['fertility']);
            }

            $this->situationForPage($v, $categories);
        }

        return array_column($data, null, 'patientid');
    }

    /**
     * 术前影像
     *
     * @param  array<string,mixed>  $patients
     * @param  array<string,mixed>  $categories
     * @return array<string,mixed>
     */
    private function video(array $patients, array $categories)
    {
        $conds = [
            'vtype'           => VideoType::IhBefore->value,
            'video.deletedat' => 0,
        ];
        $data = Video::query()
            ->where($conds)
            ->join('patient AS p', 'p.id', '=', 'video.patientid')
            ->where('p.deletedat', 0)
            ->select(explode(',', 'video.id,patientid,video.hospseq,'.$this->videoFields))
            ->get()
            ->keyBy('id')
            ->map(fn (Video $record): array => $record->getAttributes())
            ->all();
        foreach ($data as $id => &$v) {
            if (! $this->ihBeExported($patients[$v['patientid']] ?? [], $v['hospseq'])) {
                unset($data[$id]);

                continue;
            }
            unset($v['id'], $v['hospseq']);

            $this->calcVideoVol($v);
            $this->videoForPage($v, $categories);

            $v['lesionpart'] = $v['lesionposition'].'-'.$v['lesionpart'];
        }

        return array_column($data, null, 'patientid');
    }

    /**
     * 手术情况
     *
     * @param  array<string,mixed>  $patients
     * @param  array<string,mixed>  $categories
     * @return array<string,mixed>
     */
    private function surgery(array $patients, array $categories)
    {
        $conds = [
            'surgery.sytype'    => SurgeryType::Surgery->value,
            'surgery.deletedat' => 0,
        ];
        $data = Surgery::query()
            ->where($conds)
            ->join('patient AS p', 'p.id', '=', 'surgery.patientid')
            ->where('p.deletedat', 0)
            ->select(explode(',', 'surgery.id,patientid,surgery.hospseq,'.$this->surgeryFields))
            ->get()
            ->keyBy('id')
            ->map(fn (Surgery $record): array => $record->getAttributes())
            ->all();
        foreach ($data as $id => &$v) {
            if (empty($patients[$v['patientid']]) || ! $this->ihBeExported($patients[$v['patientid']], $v['hospseq'])) {
                unset($data[$id]);

                continue;
            }
            unset($v['id'], $v['hospseq']);

            $this->surgeryForPage($v);

            $v['tumorbloodsupply']   = $categories['tumorbloodsupply'][$v['tumorbloodsupply']]     ?? '';
            $v['tumortexture']       = $categories['tumortexture'][$v['tumortexture']]             ?? '';
            $v['tumoradhesionlevel'] = $categories['tumoradhesionlevel'][$v['tumoradhesionlevel']] ?? '';
        }

        return array_column($data, null, 'patientid');
    }

    /**
     * 病理
     *
     * @param  array<string,mixed>  $patients
     * @param  array<string,mixed>  $categories
     * @return array<string,mixed>
     */
    private function pathology(array $patients, array $categories)
    {
        $conds = [
            'pathology.deletedat' => 0,
        ];
        $data = Pathology::query()
            ->where($conds)
            ->join('patient AS p', 'p.id', '=', 'pathology.patientid')
            ->where('p.deletedat', 0)
            ->select(explode(',', 'pathology.id,patientid,pathology.hospseq,'.$this->pathologyFields))
            ->get()
            ->keyBy('id')
            ->map(fn (Pathology $record): array => $record->getAttributes())
            ->all();
        foreach ($data as $id => &$v) {
            if (empty($patients[$v['patientid']]) || ! $this->ihBeExported($patients[$v['patientid']], $v['hospseq'])) {
                unset($data[$id]);

                continue;
            }
            unset($v['id'], $v['hospseq']);

            $this->pathologyForPage($v, $categories);
        }

        return array_column($data, null, 'patientid');
    }

    /**
     * 出院
     *
     * @param  array<string,mixed>  $patients
     * @param  array<string,mixed>  $categories
     * @return array<string,mixed>
     */
    private function discharge(array $patients, array $categories)
    {
        $conds = [
            'stype'               => SituationType::IhDischarge->value,
            'situation.deletedat' => 0,
        ];
        $data = Situation::query()
            ->where($conds)
            ->join('patient AS p', 'p.id', '=', 'situation.patientid')
            ->where('p.deletedat', 0)
            ->select(explode(',', 'situation.id,patientid,situation.hospseq,'.$this->situationFields))
            ->get()
            ->keyBy('id')
            ->map(fn (Situation $record): array => $record->getAttributes())
            ->all();
        foreach ($data as $id => &$v) {
            if (empty($patients[$v['patientid']]) || ! $this->ihBeExported($patients[$v['patientid']], $v['hospseq'])) {
                unset($data[$id]);

                continue;
            }
            unset($v['id'], $v['hospseq']);

            $this->situationForPage($v, $categories);
        }

        return array_column($data, null, 'patientid');
    }

    /**
     * 随访情况-基本信息
     *
     * @param  array<string,mixed>  $categories
     * @return array<string,mixed>
     */
    private function followup(array $categories)
    {
        $conds = [
            'outin'               => $this->getOutInType(),
            'treatment.deletedat' => 0,
        ];
        $data = Treatment::query()
            ->where($conds)
            ->join('patient AS p', 'p.lastfollowupid', '=', 'treatment.id')
            ->where('p.deletedat', 0)
            ->select('treatment.id', 'patientid', 'checkday', 'checkway', 'checkalive')
            ->get()
            ->keyBy('id')
            ->map(fn (Treatment $record): array => $record->getAttributes())
            ->all();
        if (empty($data)) {
            return [];
        }

        foreach ($data as &$v) {
            $v['checkway']   = $categories['checkway'][$v['checkway']] ?? '';
            $v['checkalive'] = ([1 => '存活', 2 => '死亡'][$v['checkalive']] ?? '');
        }

        return array_column($data, null, 'patientid');
    }

    /**
     * 随访的基本情况
     *
     * @param  array<string,mixed>  $categories
     * @return array<string,mixed>
     */
    private function followupSituation(array $categories)
    {
        $conds = [
            'stype'               => $this->getSituationType(),
            'situation.deletedat' => 0,
        ];
        $data = Situation::query()
            ->where($conds)
            ->join('patient AS p', 'p.lastfollowupid', '=', 'situation.treatmentid')
            ->where('p.deletedat', 0)
            ->select('patientid', 'kps', 'cc', 'positive', 'epilepsy')
            ->get()
            ->map(fn (Situation $record): array => $record->getAttributes())
            ->all();
        foreach ($data as &$v) {
            $this->situationForPage($v, $categories);
        }

        return array_column($data, null, 'patientid');
    }

    /**
     * 随访的影像学
     *
     * @param  array<string,mixed>  $categories
     * @return array<string,mixed>
     */
    private function followupVideo(array $categories)
    {
        $conds = [
            'vtype'           => $this->getVideoType(),
            'video.deletedat' => 0,
        ];
        $data = Video::query()
            ->where($conds)
            ->join('patient AS p', 'p.lastfollowupid', '=', 'video.treatmentid')
            ->where('p.deletedat', 0)
            ->select(explode(',', 'patientid,'.$this->videoFields))
            ->get()
            ->map(fn (Video $record): array => $record->getAttributes())
            ->all();
        foreach ($data as &$v) {
            $this->videoForPage($v, $categories);

            $v['lesionpart'] = $v['lesionposition'].'-'.$v['lesionpart'];
        }

        return array_column($data, null, 'patientid');
    }

    /**
     * 随访的放疗
     *
     * @return array<string,mixed>
     */
    private function followupRadiotherapy()
    {
        $conds = [
            'radiotherapy.deletedat' => 0,
        ];
        $data = Radiotherapy::query()
            ->where($conds)
            ->join('patient AS p', 'p.lastfollowupid', '=', 'radiotherapy.treatmentid')
            ->where('p.deletedat', 0)
            ->select(explode(',', 'patientid,'.$this->radiotherapyFields))
            ->get()
            ->map(fn (Radiotherapy $record): array => $record->getAttributes())
            ->all();

        return array_column($data, null, 'patientid');
    }

    /**
     * 导出患者数据
     * 1，有手术记录的，导出第一次手术的住院记录
     * 2，没有手术记录的，导出最后一次住院记录
     */
    public function saveToExcel(): string
    {
        $categories = $this->systemService->categoriesKV();

        // 处理当前医院所有医生的住院患者
        $conds = ['patient.isin' => 1, 'patient.'.$this->deleteTag => 0, 'patient.deletedat' => 0];

        // 超级用户可以下载所有数据
        if (! isAdministrator()) {
            $conds['patient.hospitalid'] = currentHospitalId();
        }

        $fields = static::INHOSPITAL_FIELDS;

        // 基本信息
        $patients = Patient::query()
            ->where($conds)
            ->select(explode(',', 'id,hospitalid,hospseq,surgeryseq,'.implode(',', array_keys($fields['基本信息']))))
            ->orderBy('id', 'desc')
            ->get()
            ->keyBy('id')
            ->map(fn (Patient $record): array => $record->getAttributes())
            ->all();

        // 添加医院名称到患者信息内
        $hospitals = array_column(Hospital::query()
            ->select('hospital.*')
            ->get()
            ->keyBy('id')
            ->map(fn (Hospital $record): array => $record->getAttributes())
            ->all(), 'title', 'id');
        foreach ($patients as &$patient) {
            $patient['hospital'] = $hospitals[$patient['hospitalid']];
        }

        // 入院情况
        $situations = $this->basic($patients, $categories);

        // 术前影像
        $video = $this->video($patients, $categories);

        // 手术情况
        $surgeries = $this->surgery($patients, $categories);

        // 病理
        $pathologies = $this->pathology($patients, $categories);

        // 出院
        $discharges = $this->discharge($patients, $categories);

        // 随访情况(取最后一次随访记录)
        // 基本情况
        $fuBasics = $this->followup($categories);
        // 患者情况
        $fuSta = $this->followupSituation($categories);
        // 影像学
        $fuVideo = $this->followupVideo($categories);
        // 放疗
        $fuRad = $this->followupRadiotherapy();

        $get = function ($data, $field) use ($fields) {

            $row = [];
            if ($field == '基本信息') {
                $row['医院'] = $data['hospital'];
            }

            foreach ($fields[$field] ?? [] as $f => $title) {
                $row[$f] = $data[$f] ?? '';
            }

            return array_values($row);
        };

        $data = [];
        foreach ($patients as $id => $patient) {
            $patient['idcard'] = $patient['idcard'] ? $patient['idcard'].' ' : '';
            $this->patientForPage($patient);

            $basic = $get($patient, '基本信息');
            $sta   = $get($situations[$id] ?? [], '入院情况');
            $vdo   = $get($video[$id] ?? [], '术前影像');
            $surg  = $get($surgeries[$id] ?? [], '手术情况');
            $pog   = $get($pathologies[$id] ?? [], '病理');
            $dcg   = $get($discharges[$id] ?? [], '出院');

            $fubasic = $get($fuBasics[$id] ?? [], '随访-基本情况');
            $fusta   = $get($fuSta[$id] ?? [], '随访-患者情况');
            $fuvideo = $get($fuVideo[$id] ?? [], '随访-影像学检查');
            $furad   = $get($fuRad[$id] ?? [], '随访-放疗');

            $data[] = array_merge($basic, $sta, $vdo, $surg, $pog, $dcg, $fubasic, $fusta, $fuvideo, $furad);
        }

        $disk = Storage::disk('local');
        $disk->makeDirectory('exports');
        $filename = $disk->path('exports/patients-'.Str::random(10).'.xlsx');

        $tmpl        = base_path('docs/patient_ih.xlsx');
        $spreadsheet = IOFactory::load($tmpl);
        $worksheet   = $spreadsheet->getActiveSheet();

        // 前3行是标题
        $worksheet->fromArray($data, null, 'A4');

        $writer = IOFactory::createWriter($spreadsheet, 'Xlsx');
        $writer->save($filename);

        return $filename;
    }

    /**
     * 保存为word文件
     */
    public function saveToWord(): string
    {
        $disk = Storage::disk('local');
        $disk->makeDirectory('exports');
        $filename = $disk->path('exports/patient-'.Str::random(10).'.docx');
        $patient  = $this->getPatient();

        $templateProcessor = new TemplateProcessor(base_path('docs/patient_inhosp.docx'));

        $templateProcessor->setValue('realname', $patient['realname']);
        $templateProcessor->setValue('gender', getGenderText($patient['gender']));

        $templateProcessor->saveAs($filename);

        return $filename;
    }
}
