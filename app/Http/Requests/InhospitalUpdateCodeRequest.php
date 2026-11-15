<?php

declare(strict_types=1);

namespace App\Http\Requests;

class InhospitalUpdateCodeRequest extends RegistryRequest
{
    /**
     * @return array<string,mixed>
     */
    protected function defaults(): array
    {
        return [
            'hospcode' => '',
            'hospday'  => '',
        ];
    }

    /**
     * @return array<string,mixed>
     */
    public function rules(): array
    {
        return [
            'hospcode' => ['string'],
            'hospday'  => ['string'],
        ];
    }
}
