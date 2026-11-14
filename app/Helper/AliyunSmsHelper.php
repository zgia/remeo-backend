<?php

namespace App\Helper;

use Illuminate\Support\Facades\Http;

/**
 * Class AliyunSmsHelper
 */
class AliyunSmsHelper
{
    public string $signName = '天坛remeo';

    public function setSignName(string $name): void
    {
        $this->signName = $name;
    }

    /**
     * 短信模板
     *
     *
     * @return array<string,string>
     */
    public function getSmsTmpl(string $mobile, string $code, string $type = 'code')
    {
        // @phpstan-ignore-next-line
        $tmpl = match ($type) {
            'code' => sprintf('{"code":"%s"}', $code),
            'pwd'  => sprintf('{"pwd":"%s"}', $code),
        };

        // @phpstan-ignore-next-line
        $tmplCode = match ($type) {
            'code' => 'SMS_255920356',
            'pwd'  => 'SMS_257645509',
        };

        return [
            'signName'      => $this->signName,
            'templateCode'  => $tmplCode,
            'phoneNumbers'  => $mobile,
            'templateParam' => $tmpl,
        ];
    }

    /**
     * 发送信息
     */
    public function sendSMS(string $mobile, string $code, string $type): void
    {
        Http::asForm()->connectTimeout(5)->timeout(15)->post(config('remeo.sms_url'), $this->getSmsTmpl($mobile, $code, $type))->throw();
    }
}
