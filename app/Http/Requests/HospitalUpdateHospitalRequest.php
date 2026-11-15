<?php

declare(strict_types=1);

namespace App\Http\Requests;

class HospitalUpdateHospitalRequest extends RegistryRequest
{
    /**
     * @return array<string,mixed>
     */
    protected function defaults(): array
    {
        return [
            'title'      => '',
            'hospitalid' => 0,
        ];
    }

    /**
     * @return array<string,mixed>
     */
    public function rules(): array
    {
        return [
            'title'      => ['string'],
            'hospitalid' => ['integer'],
        ];
    }
}
