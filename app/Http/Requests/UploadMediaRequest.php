<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/** 接口校验，沿用手机号和原字段名称。 */
class UploadMediaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string,mixed> */
    public function rules(): array
    {
        return ['patientid' => ['required', 'integer', 'min:1'], 'file' => ['required', 'image', 'mimes:jpg,jpeg,png', 'extensions:jpg,jpeg,png', 'max:10240'], 'uid' => ['nullable', 'string']];
    }
}
