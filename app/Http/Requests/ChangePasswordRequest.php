<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * 接口校验，沿用手机号和原字段名称。
 */
class ChangePasswordRequest extends FormRequest
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
        return ['password' => ['required', 'string', 'current_password'], 'password1' => ['required', 'string', 'min:'.getOption('pwdminlen', 6), 'max:200'], 'password2' => ['required', 'same:password1']];
    }
}
