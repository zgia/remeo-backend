<?php

declare(strict_types=1);

namespace App\Exception;

/** 携带业务定位信息的异常。 */
class BusinessException extends \RuntimeException
{
    private array $more = [];

    /** @param array<string,mixed> $more @return void */
    public function setMore(array $more = []): void
    {
        $this->more = $more;
    }

    /** @return array<string,mixed> */
    public function getMore(): array
    {
        return $this->more;
    }
}
