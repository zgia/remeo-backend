<?php

namespace App\Service;

use App\Enums\ConstantType;
use App\Enums\SituationType;
use App\Enums\VideoType;
use App\Models\Patient;
use App\Models\Situation;
use App\Models\Treatment;
use App\Models\Video;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpWord\TemplateProcessor;

/**
 * Class ExportOutpatientService
 */
class ExportOutpatientService extends OutpatientService
{
    public const OUTPATIENT_FIELDS = [
        '基本信息' => [
            'realname'     => '姓名',
            'gender'       => '性别',
            'age'          => '年龄',
            'idcard'       => '身份证',
            'mobile'       => '联系方式',
            'firstday'     => '首诊日期',
            'checkcount'   => '复查次数',
            'lastcheckday' => '末次复查时间',
            'nextcheckday' => '下次复查时间',
        ],
        '首诊情况' => [
            'kps'                => 'KPS',
            'cc'                 => '主诉',
            'positive'           => '阳性体征',
            'uterinefibroids'    => '子宫肌瘤',
            'menopause'          => '绝经',
            'pastmedicalhistory' => '其他既往史',
        ],
        '影像学检查' => [
            'videostatus'   => '本次影像学检查',
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
            'ct'            => '是否做过CT',
            'ctcalc'        => 'CT是否有钙化',
        ],
    ];

    /**
     * 复诊(复查)记录，参考住院患者的随访数据
     *
     * 门诊患者可能有多次复诊，导出时每一次复诊占用一组相同的列
     */
    public const CHECK_FIELDS = [
        '基本情况' => [
            'checkday'   => '复查日期',
            'checkway'   => '复查方式',
            'checkalive' => '是否存活',
        ],
        '患者情况' => [
            'kps'      => 'KPS',
            'cc'       => '主诉',
            'positive' => '阳性体征',
        ],
        '影像学检查' => [
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
    ];

    /**
     * 复诊标题的样式，参考住院随访(patient_ih.xlsx)的标题
     */
    public const CHECK_HEAD_STYLE = [
        'font'      => ['name' => '等线', 'size' => 12],
        'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FFD8D8D8']],
        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
        'borders'   => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
    ];

    /**
     * 复诊字段对应的模板列，用于复制列宽
     */
    public const CHECK_WIDTH_COLUMNS = [
        'kps'           => 'J',
        'cc'            => 'K',
        'positive'      => 'L',
        'videostatus'   => 'P',
        'videoday'      => 'Q',
        'sequence'      => 'R',
        'lesioncount'   => 'S',
        'lesionpart'    => 'T',
        'sinus'         => 'U',
        'cemrilength'   => 'V',
        'cemriwidth'    => 'W',
        'cemrithick'    => 'X',
        'cemrivol'      => 'Y',
        't2flairlength' => 'Z',
        't2flairwidth'  => 'AA',
        't2flairthick'  => 'AB',
        't2flairvol'    => 'AC',
        'edemavol'      => 'AD',
        'videoresult'   => 'AE',
    ];

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
            'stype'               => SituationType::OpFisrt->value,
            'situation.deletedat' => 0,
        ];
        $data = Situation::query()
            ->where($conds)
            ->join('patient AS p', 'p.id', '=', 'situation.patientid')
            ->where('p.deletedat', 0)
            ->select(explode(',', 'situation.id,patientid,'.$this->situationFields))
            ->get()
            ->keyBy('id')
            ->map(fn (Situation $record): array => $record->getAttributes())
            ->all();

        foreach ($data as $id => &$v) {
            if (empty($patients[$v['patientid']])) {
                unset($data[$id]);

                continue;
            }

            $v['isfemale'] = isFemale($patients[$v['patientid']]['gender']);

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
            'vtype'           => VideoType::OpFisrt->value,
            'video.deletedat' => 0,
        ];
        $data = Video::query()
            ->where($conds)
            ->join('patient AS p', 'p.id', '=', 'video.patientid')
            ->where('p.deletedat', 0)
            ->select(explode(',', 'video.id,patientid,lesionposition,'.implode(',', array_keys(static::OUTPATIENT_FIELDS['影像学检查']))))
            ->get()
            ->keyBy('id')
            ->map(fn (Video $record): array => $record->getAttributes())
            ->all();
        foreach ($data as $id => &$v) {
            if (empty($patients[$v['patientid']])) {
                unset($data[$id]);

                continue;
            }

            $this->calcVideoVol($v);
            $this->videoForPage($v, $categories);

            $v['ct']         = static::CATEGORY_CT[$v['ct']]         ?? '';
            $v['ctcalc']     = static::CATEGORY_CTCALC[$v['ctcalc']] ?? '';
            $v['lesionpart'] = $v['lesionposition'].'-'.$v['lesionpart'];
        }

        return array_column($data, null, 'patientid');
    }

    /**
     * 复诊(复查)记录
     *
     * 门诊患者可能有多次复诊，这里全部取出来，按复诊日期排序
     *
     * @param  array<string,mixed>  $patients
     * @param  array<string,mixed>  $categories
     * @return array<int,array<int,array<string,mixed>>> [patientid => [复诊记录, ...]]
     */
    private function checks(array $patients, array $categories)
    {
        $treatments = Treatment::query()
            ->where('treatment.outin', ConstantType::HospitalizedOutpatient->value)
            ->where('treatment.step', '<>', 1)
            ->where('treatment.deletedat', 0)
            ->select('id', 'patientid', 'checkday', 'checkway', 'checkalive')
            ->orderBy('checkday', 'asc')
            ->orderBy('id', 'asc')
            ->get()
            ->keyBy('id')
            ->map(fn (Treatment $record): array => $record->getAttributes())
            ->all();

        if (empty($treatments)) {
            return [];
        }

        // 患者情况
        $situations = Situation::query()
            ->where('situation.stype', SituationType::OpCheck->value)
            ->where('situation.deletedat', 0)
            ->select(explode(',', 'treatmentid,'.$this->situationFields))
            ->get()
            ->map(fn (Situation $record): array => $record->getAttributes())
            ->all();

        // 影像学检查
        $videos = Video::query()
            ->where('video.vtype', VideoType::OpCheck->value)
            ->where('video.deletedat', 0)
            ->select(explode(',', 'treatmentid,'.$this->videoFields))
            ->get()
            ->map(fn (Video $record): array => $record->getAttributes())
            ->all();

        $situations = array_column($situations, null, 'treatmentid');
        $videos     = array_column($videos, null, 'treatmentid');

        $data = [];
        foreach ($treatments as $treatment) {
            if (empty($patients[$treatment['patientid']])) {
                continue;
            }

            $treatmentid = (int) $treatment['id'];

            $check = [
                'checkday'   => $treatment['checkday']                          ?? '',
                'checkway'   => $categories['checkway'][$treatment['checkway']] ?? '',
                'checkalive' => static::getAliveOrDeath($treatment['checkalive']),
            ];

            foreach (static::CHECK_FIELDS['患者情况'] as $field => $title) {
                $check[$field] = $situations[$treatmentid][$field] ?? '';
            }

            $video = $videos[$treatmentid] ?? [];
            if ($video) {
                $this->calcVideoVol($video);
                $this->videoForPage($video, $categories);

                $video['lesionpart'] = $video['lesionposition'].'-'.$video['lesionpart'];
            }

            foreach (static::CHECK_FIELDS['影像学检查'] as $field => $title) {
                $check[$field] = $video[$field] ?? '';
            }

            $data[$treatment['patientid']][] = $check;
        }

        return $data;
    }

    /**
     * 复诊记录的字段，按导出顺序
     *
     * @return array<string,string>
     */
    private static function checkColumns(): array
    {
        $columns = [];
        foreach (static::CHECK_FIELDS as $fields) {
            foreach ($fields as $field => $title) {
                $columns[$field] = $title;
            }
        }

        return $columns;
    }

    /**
     * 写入复诊记录的标题
     *
     * 第1行表示第几次复诊，第2行是基本情况、患者情况、影像学检查，第3行是字段名称
     *
     * @param  int  $startColumn  复诊记录的开始列(从1开始)
     * @param  int  $count  复诊次数
     */
    private function checkHeadings(Worksheet $worksheet, int $startColumn, int $count): void
    {
        // 一次复诊占用的列数
        $total = count(self::checkColumns());

        // 模板列的宽度
        $widths = [];
        foreach (static::CHECK_WIDTH_COLUMNS as $field => $column) {
            $widths[$field] = $worksheet->getColumnDimension($column)->getWidth();
        }

        $defaultWidth = $worksheet->getDefaultColumnDimension()->getWidth();

        for ($i = 0; $i < $count; $i++) {
            $begin = $startColumn + $i * $total;

            // 第1行: 第1次复诊、第2次复诊 ...
            $this->checkHeadCell($worksheet, $begin, $begin + $total - 1, 1, '第'.($i + 1).'次复诊');

            $column = $begin;
            foreach (static::CHECK_FIELDS as $group => $fields) {
                $span = count($fields);

                // 第2行: 基本情况、患者情况、影像学检查
                $this->checkHeadCell($worksheet, $column, $column + $span - 1, 2, $group);

                // 第3行: 字段名称
                foreach ($fields as $field => $title) {
                    $this->checkHeadCell($worksheet, $column, $column, 3, $title);

                    $width = $widths[$field] ?? -1;
                    $worksheet->getColumnDimension(Coordinate::stringFromColumnIndex($column))->setWidth($width > 0 ? $width : $defaultWidth);

                    $column++;
                }
            }
        }
    }

    /**
     * 写入一个复诊标题单元格
     */
    private function checkHeadCell(Worksheet $worksheet, int $begin, int $end, int $row, string $title): void
    {
        $range = Coordinate::stringFromColumnIndex($begin).$row.':'.Coordinate::stringFromColumnIndex($end).$row;

        if ($begin < $end) {
            $worksheet->mergeCells($range);
        }

        $worksheet->setCellValue(Coordinate::stringFromColumnIndex($begin).$row, $title);
        $worksheet->getStyle($range)->applyFromArray(static::CHECK_HEAD_STYLE);
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
        $conds = ['patient.isin' => 0, 'patient.'.$this->deleteTag => 0, 'patient.deletedat' => 0];

        // 超级用户可以下载所有数据
        if (! isAdministrator()) {
            $conds['patient.hospitalid'] = currentHospitalId();
        }

        $fields = static::OUTPATIENT_FIELDS;

        // 基本信息
        $patients = Patient::query()
            ->where($conds)
            ->select(explode(',', 'id,'.implode(',', array_keys($fields['基本信息']))))
            ->orderBy('id', 'desc')
            ->get()
            ->keyBy('id')
            ->map(fn (Patient $record): array => $record->getAttributes())
            ->all();

        // 首诊情况
        $situations = $this->basic($patients, $categories);

        // 影像学检查
        $video = $this->video($patients, $categories);

        // 复诊记录，门诊患者可能有多次复诊
        $checks = $this->checks($patients, $categories);

        $get = function ($data, $field) use ($fields) {
            $row = [];
            foreach ($fields[$field] ?? [] as $f => $title) {
                $row[$f] = $data[$f] ?? '';
            }

            return array_values($row);
        };

        // 复诊的字段，以及所有患者中最多复诊了几次
        $checkFields = array_keys(self::checkColumns());
        $checkcount  = 0;
        foreach ($checks as $check) {
            $checkcount = max($checkcount, count($check));
        }

        $data = [];
        foreach ($patients as $id => $patient) {
            $patient['idcard'] = $patient['idcard'] ? $patient['idcard'].' ' : '';
            $this->patientForPage($patient);

            $basic = $get($patient, '基本信息');
            $sta   = $get($situations[$id] ?? [], '首诊情况');
            $vdo   = $get($video[$id] ?? [], '影像学检查');

            $row = array_merge($basic, $sta, $vdo);

            // 每次复诊占用一组相同的列，没有复诊的补空
            for ($i = 0; $i < $checkcount; $i++) {
                $check = $checks[$id][$i] ?? [];

                foreach ($checkFields as $f) {
                    $row[] = $check[$f] ?? '';
                }
            }

            $data[] = $row;
        }

        $disk = Storage::disk('local');
        $disk->makeDirectory('exports');
        $filename = $disk->path('exports/patients-'.Str::random(10).'.xlsx');

        $tmpl        = base_path('docs/patient_op.xlsx');
        $spreadsheet = IOFactory::load($tmpl);
        $worksheet   = $spreadsheet->getActiveSheet();

        // 前3行是标题
        $worksheet->fromArray($data, null, 'A4');

        // 复诊记录的标题
        if ($checkcount) {
            $baseColumns = 0;
            foreach ($fields as $fs) {
                $baseColumns += count($fs);
            }

            $this->checkHeadings($worksheet, $baseColumns + 1, $checkcount);
        }

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
