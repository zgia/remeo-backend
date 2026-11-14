<?php

declare(strict_types=1);

namespace App\Http\Requests;

class PatientUpdateVideoRequest extends RegistryRequest
{
    /** @return array<string,mixed> */
    protected function defaults(): array
    {
        return [
            'sinus'          => 0,
            'ct'             => 0,
            'ctcalc'         => 0,
            'cemrilength'    => 0,
            'cemriwidth'     => 0,
            'cemrithick'     => 0,
            't2flairlength'  => 0,
            't2flairwidth'   => 0,
            't2flairthick'   => 0,
            'lesionposition' => 0,
            'lesionpart'     => '',
            'lesioncount'    => '',
            'sequence'       => 0,
            'videoday'       => '',
            'videostatus'    => 0,
            'patientid'      => 0,
            'vtype'          => '',
        ];
    }

    /** @return array<string,mixed> */
    public function rules(): array
    {
        return [
            'sinus'          => ['integer'],
            'ct'             => ['integer'],
            'ctcalc'         => ['integer'],
            'cemrilength'    => ['integer'],
            'cemriwidth'     => ['integer'],
            'cemrithick'     => ['integer'],
            't2flairlength'  => ['integer'],
            't2flairwidth'   => ['integer'],
            't2flairthick'   => ['integer'],
            'lesionposition' => ['integer'],
            'lesionpart'     => ['string'],
            'lesioncount'    => ['string'],
            'sequence'       => ['integer'],
            'videoday'       => ['string'],
            'videostatus'    => ['integer'],
            'patientid'      => ['integer'],
            'vtype'          => ['string'],
        ];
    }
}
