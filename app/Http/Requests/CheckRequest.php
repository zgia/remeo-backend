<?php

declare(strict_types=1);

namespace App\Http\Requests;

class CheckRequest extends RegistryRequest
{
    protected function prepareForValidation(): void
    {
        $video = $this->input('video');
        if (is_array($video)) {
            // 空表单值沿用数据库的 0 默认值；其它非法输入保留给 Validator 拒绝。
            foreach (['cemrilength', 'cemriwidth', 'cemrithick', 't2flairlength', 't2flairwidth', 't2flairthick'] as $field) {
                if (($video[$field] ?? null) === '') {
                    $video[$field] = 0;
                }
            }
            // 日期缺失表示尚未记录，不能把空字符串写入 MySQL DATE 字段。
            if (($video['videoday'] ?? null) === '') {
                $video['videoday'] = null;
            }
            $this->merge(['video' => $video]);
        }
        parent::prepareForValidation();
    }

    /**
     * @return array<string,mixed>
     */
    protected function defaults(): array
    {
        return [
            'situation.positive'        => '',
            'situation.cc'              => '',
            'situation.kps'             => 0,
            'situation.epilepsy'        => 0,
            'video.sinus'               => 0,
            'video.ct'                  => 0,
            'video.ctcalc'              => 0,
            'video.cemrilength'         => 0,
            'video.cemriwidth'          => 0,
            'video.cemrithick'          => 0,
            'video.t2flairlength'       => 0,
            'video.t2flairwidth'        => 0,
            'video.t2flairthick'        => 0,
            'video.lesionposition'      => 0,
            'video.lesionpart'          => '',
            'video.lesioncount'         => '',
            'video.sequence'            => 0,
            'video.videoday'            => null,
            'video.videostatus'         => 0,
            'video.videoresult'         => 0,
            'video.suggestion'          => '',
            'radiotherapy.treatmentid'  => 0,
            'radiotherapy.ryaction'     => 0,
            'radiotherapy.rysyinterval' => 0,
            'radiotherapy.rycount'      => 0,
            'radiotherapy.rydoses'      => '',
            'treatment.treatmentid'     => 0,
            'treatment.checkday'        => '',
            'treatment.checkway'        => 0,
            'treatment.checkalive'      => 0,
            'treatment.causeofdeath'    => '',
        ];
    }

    /**
     * @return array<string,mixed>
     */
    public function rules(): array
    {
        return [
            'situation'                 => ['array'],
            'video'                     => ['array'],
            'radiotherapy'              => ['array'],
            'treatment'                 => ['array'],
            'situation.positive'        => ['string'],
            'situation.cc'              => ['string'],
            'situation.kps'             => ['integer'],
            'situation.epilepsy'        => ['integer'],
            'video.sinus'               => ['integer'],
            'video.ct'                  => ['integer'],
            'video.ctcalc'              => ['integer'],
            'video.cemrilength'         => ['numeric', 'integer', 'min:0', 'max:65535'],
            'video.cemriwidth'          => ['numeric', 'integer', 'min:0', 'max:65535'],
            'video.cemrithick'          => ['numeric', 'integer', 'min:0', 'max:65535'],
            'video.t2flairlength'       => ['numeric', 'integer', 'min:0', 'max:65535'],
            'video.t2flairwidth'        => ['numeric', 'integer', 'min:0', 'max:65535'],
            'video.t2flairthick'        => ['numeric', 'integer', 'min:0', 'max:65535'],
            'video.lesionposition'      => ['integer'],
            'video.lesionpart'          => ['string'],
            'video.lesioncount'         => ['string'],
            'video.sequence'            => ['integer'],
            'video.videoday'            => ['nullable', 'date_format:Y-m-d'],
            'video.videostatus'         => ['integer'],
            'video.videoresult'         => ['integer'],
            'video.suggestion'          => ['string'],
            'radiotherapy.treatmentid'  => ['integer'],
            'radiotherapy.ryaction'     => ['integer'],
            'radiotherapy.rysyinterval' => ['integer'],
            'radiotherapy.rycount'      => ['integer'],
            'radiotherapy.rydoses'      => ['string'],
            'treatment.treatmentid'     => ['integer'],
            'treatment.checkday'        => ['string'],
            'treatment.checkway'        => ['integer'],
            'treatment.checkalive'      => ['integer'],
            'treatment.causeofdeath'    => ['string'],
        ];
    }
}
