<?php

namespace App\Logging;

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;
use Monolog\Formatter\JsonFormatter;
use Monolog\LogRecord;

class CustomizeJsonFormatter extends JsonFormatter
{
    /**
     * @return array<array<mixed>|bool|float|int|\stdClass|string|null>
     */
    protected function normalizeRecord(LogRecord $record): array
    {
        $vars = parent::normalizeRecord($record->with(
            context: Arr::except($record->context, ['request_id'])
        ));

        $vars['request_id'] = Arr::get(Log::sharedContext(), 'request_id');
        $vars['ip']         = request()->getClientIp();
        $vars['method']     = request()->method();
        $vars['path']       = request()->path();
        // 密码不能记录到日志中，参数保留结构，避免重复 JSON 编码。
        $vars['param'] = $this->normalizeValue(Arr::except(request()->all(), ['password', 'old_password', 'password_confirmation']));

        return $vars;
    }
}
