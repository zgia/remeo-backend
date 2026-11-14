<?php

declare(strict_types=1);

namespace App\Http\Requests;

class OutpatientUpdateSituationRequest extends RegistryRequest
{
    /** @return array<string,mixed> */
    protected function defaults(): array
    {
        return [
            'positive'           => '',
            'cc'                 => '',
            'kps'                => 0,
            'uterinefibroids'    => 0,
            'menopause'          => 0,
            'pastmedicalhistory' => '',
            'patientid'          => 0,
        ];
    }

    /** @return array<string,mixed> */
    public function rules(): array
    {
        return [
            'positive'           => ['string'],
            'cc'                 => ['string'],
            'kps'                => ['integer'],
            'uterinefibroids'    => ['integer'],
            'menopause'          => ['integer'],
            'pastmedicalhistory' => ['string'],
            'patientid'          => ['integer'],
        ];
    }
}
