<?php

declare(strict_types=1);

namespace App\Http\Requests;

class InhospitalUpdateSurgeryRequest extends RegistryRequest
{
    /** @return array<string,mixed> */
    protected function defaults(): array
    {
        return [
            'surgeryday'         => '',
            'surgeryway'         => 0,
            'bleedingvol'        => 0,
            'alloredcellsursp'   => 0,
            'alloplasma'         => 0,
            'tumorbloodsupply'   => 0,
            'tumortexture'       => 0,
            'tumoradhesionlevel' => 0,
            'surgerydesc'        => '',
            'surgeryafter'       => '',
            'patientid'          => 0,
        ];
    }

    /** @return array<string,mixed> */
    public function rules(): array
    {
        return [
            'surgeryday'         => ['string'],
            'surgeryway'         => ['integer'],
            'bleedingvol'        => ['integer'],
            'alloredcellsursp'   => ['integer'],
            'alloplasma'         => ['integer'],
            'tumorbloodsupply'   => ['integer'],
            'tumortexture'       => ['integer'],
            'tumoradhesionlevel' => ['integer'],
            'surgerydesc'        => ['string'],
            'surgeryafter'       => ['string'],
            'patientid'          => ['integer'],
        ];
    }
}
