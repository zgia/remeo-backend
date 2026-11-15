<?php

declare(strict_types=1);

namespace App\Http\Requests;

class OutpatientUpdatePatientRequest extends RegistryRequest
{
    /**
     * @return array<string,mixed>
     */
    protected function defaults(): array
    {
        return [
            'realname' => '',
            'age'      => 0,
            'idcard'   => '',
            'gender'   => 0,
            'firstday' => '',
            'mobile'   => '',
            'linkman'  => '',
            'linkrel'  => '',
            'mobile2'  => '',
            'linkman2' => '',
            'linkrel2' => '',
            'mobile3'  => '',
            'linkman3' => '',
            'linkrel3' => '',
        ];
    }

    /**
     * @return array<string,mixed>
     */
    public function rules(): array
    {
        return [
            'realname' => ['string'],
            'age'      => ['integer', 'min:0'],
            'idcard'   => ['string'],
            'gender'   => ['integer'],
            'firstday' => ['string'],
            'mobile'   => ['string'],
            'linkman'  => ['string'],
            'linkrel'  => ['string'],
            'mobile2'  => ['string'],
            'linkman2' => ['string'],
            'linkrel2' => ['string'],
            'mobile3'  => ['string'],
            'linkman3' => ['string'],
            'linkrel3' => ['string'],
        ];
    }
}
