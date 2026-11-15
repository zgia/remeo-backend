<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * 接口校验，沿用手机号和原字段名称。
 */
class LoginRequest extends FormRequest
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
        return [
            'mobile'   => ['required', 'string', 'regex:/^1[3-9][0-9]{9}$/'],
            'password' => ['required', 'string', 'max:200'],
            'captcha'  => ['bail', 'required', 'string', 'max:10', 'captcha'],
        ];
    }

    /**
     * @return array<string,string>
     */
    public function messages(): array
    {
        return [
            'captcha.required' => '请输入图形验证码。',
            'captcha.string'   => '验证码格式有误。',
            'captcha.max'      => '验证码格式有误。',
            'captcha.captcha'  => '验证码错误或已过期，请刷新后重试。',
        ];
    }
}
