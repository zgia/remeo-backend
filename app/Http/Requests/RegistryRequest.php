<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Arr;

/** 保留旧表的缺省字段，避免未提交的字段在迁移后变成 SQL NULL。 */
abstract class RegistryRequest extends FormRequest
{
    /** @return array<string,mixed> */
    abstract protected function defaults(): array;

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $values = [];
        foreach ($this->defaults() as $field => $default) {
            $parent = explode('.', $field)[0];
            // 错误的嵌套类型交给 Validator 拒绝，不能被默认值覆盖。
            if (str_contains($field, '.') && $this->input($parent) !== null && ! is_array($this->input($parent))) {
                continue;
            }
            if ($this->input($field) === null) {
                Arr::set($values, $field, $default);
            }
        }
        $this->replace(array_replace_recursive($this->all(), $values));
    }
}
