<?php

declare(strict_types=1);

namespace App\Controller;

use Illuminate\Http\JsonResponse;

/** 统一响应格式，保留前端 code/msg/data 契约。 */
abstract class BaseController
{
    /**
     * @param  array<mixed>|null  $data
     */
    protected function success(?string $message = null, ?array $data = null, int $status = 200): JsonResponse
    {
        return $this->respond($message, 0, $data, $status);
    }

    /**
     * @param  array<mixed>|null  $data
     */
    protected function failure(?string $message = null, ?array $data = null, int $status = 400): JsonResponse
    {
        return $this->respond($message, 1, $data, $status);
    }

    /**
     * @param  array<mixed>|null  $data
     */
    protected function respond(?string $message = null, int $code = 0, ?array $data = null, int $status = 200): JsonResponse
    {
        return response()->json(['code' => $code, 'msg' => $message ?? '', 'data' => $data ?? (object) []], $status);
    }
}
