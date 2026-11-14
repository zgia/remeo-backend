<?php

declare(strict_types=1);

namespace App\Http\Requests;

class DoctorUpdateDoctorRequest extends RegistryRequest
{
    /** @return array<string,mixed> */
    protected function defaults(): array
    {
        return [
            'realname'    => '',
            'mobile'      => '',
            'hospitalid'  => 0,
            'doctorid'    => 0,
            'permissions' => [],
        ];
    }

    /** @return array<string,mixed> */
    public function rules(): array
    {
        return [
            'realname'    => ['string'],
            'mobile'      => ['string'],
            'hospitalid'  => ['integer'],
            'doctorid'    => ['integer'],
            'permissions' => ['array'],
        ];
    }
}
