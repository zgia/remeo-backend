<?php

namespace App\Logging;

use Illuminate\Log\Logger;
use Monolog\Handler\FormattableHandlerInterface;

class CustomizeFormatter
{
    /**
     * 自定义给定的日志记录器实例。
     */
    public function __invoke(Logger $logger): void
    {
        foreach ($logger->getHandlers() as $handler) {
            if (! $handler instanceof FormattableHandlerInterface) {
                continue;
            }

            $formatter = new CustomizeJsonFormatter(
                ignoreEmptyContextAndExtra: true,
                includeStacktraces: true
            );
            $formatter->setDateFormat('Y-m-d H:i:s.v');
            $formatter->setBasePath(base_path());

            $handler->setFormatter($formatter);
        }
    }
}
