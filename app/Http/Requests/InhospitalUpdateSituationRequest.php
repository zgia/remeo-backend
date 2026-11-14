<?php

declare(strict_types=1);

namespace App\Http\Requests;

class InhospitalUpdateSituationRequest extends RegistryRequest
{
    /** @return array<string,mixed> */
    protected function defaults(): array
    {
        return [
            'positive'   => '',
            'cc'         => '',
            'kps'        => 0,
            'patientid'  => 0,
            'epilepsy'   => 0,
            'uterectomy' => 0,
            'fertility'  => [],
            'comefrom'   => '',
            'otherdesc'  => '',
        ];
    }

    /** @return array<string,mixed> */
    public function rules(): array
    {
        return [
            'positive'   => ['string'],
            'cc'         => ['string'],
            'kps'        => ['integer'],
            'patientid'  => ['integer'],
            'epilepsy'   => ['integer'],
            'uterectomy' => ['integer'],
            'fertility'  => ['array'],
            'comefrom'   => ['string'],
            'otherdesc'  => ['string'],
        ];
    }
}
