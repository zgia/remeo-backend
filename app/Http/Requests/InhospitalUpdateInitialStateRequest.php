<?php

declare(strict_types=1);

namespace App\Http\Requests;

class InhospitalUpdateInitialStateRequest extends RegistryRequest
{
    /**
     * @return array<string,mixed>
     */
    protected function defaults(): array
    {
        return [
            'data'      => [],
            'patientid' => 0,
        ];
    }

    /**
     * @return array<string,mixed>
     */
    public function rules(): array
    {
        return [
            'data'      => ['array'],
            'patientid' => ['integer'],
        ];
    }
}
