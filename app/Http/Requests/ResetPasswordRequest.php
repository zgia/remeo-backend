<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * 接口校验，沿用手机号和原字段名称。
 */
class ResetPasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string,mixed>
     */
    public function rules(): array
    {
        return ['mobile' => ['required', 'string', 'regex:/^1[3-9][0-9]{9}$/'], 'smscode' => ['required', 'digits:6']];
    }
}
