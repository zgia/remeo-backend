<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PatientListRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string,mixed> */
    public function rules(): array
    {
        return [
            'realname'   => ['nullable', 'string'],
            'hospitalid' => ['nullable', 'integer', 'min:-1'],
            'p'          => ['nullable', 'integer', 'min:1'],
            'sort'       => ['nullable', Rule::in([
                'id', 'realname', 'age', 'gender', 'firstday', 'hospday', 'hospcode', 'hospseq',
                'checkcount', 'followupcount', 'lastcheckday', 'nextcheckday',
                'lastfollowupday', 'nextfollowupday', 'created_at', 'updated_at',
            ])],
            'order' => ['nullable', Rule::in(['ascending', 'descending', 'asc', 'desc', 'ASC', 'DESC'])],
        ];
    }
}
