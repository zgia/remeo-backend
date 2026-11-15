<?php

declare(strict_types=1);

namespace App\Http\Requests;

class InhospitalUpdatePathologyRequest extends RegistryRequest
{
    /**
     * @return array<string,mixed>
     */
    protected function defaults(): array
    {
        return [
            'pycode'         => '',
            'histopathology' => 0,
            'who'            => 0,
            'ki67'           => '',
            'tertc228t'      => 0,
            'tertc250t'      => 0,
            'cdkn2a'         => 0,
            'cdkn2b'         => 0,
            'pten'           => 0,
            'h3k27m'         => 0,
            'pik3ca'         => 0,
            'smarcb1'        => 0,
            'pydesc'         => '',
            'patientid'      => 0,
        ];
    }

    /**
     * @return array<string,mixed>
     */
    public function rules(): array
    {
        return [
            'pycode'         => ['string'],
            'histopathology' => ['integer'],
            'who'            => ['integer'],
            'ki67'           => ['string'],
            'tertc228t'      => ['integer'],
            'tertc250t'      => ['integer'],
            'cdkn2a'         => ['integer'],
            'cdkn2b'         => ['integer'],
            'pten'           => ['integer'],
            'h3k27m'         => ['integer'],
            'pik3ca'         => ['integer'],
            'smarcb1'        => ['integer'],
            'pydesc'         => ['string'],
            'patientid'      => ['integer'],
        ];
    }
}
