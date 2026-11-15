<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\Patient;

class PatientRepository
{
    /**
     * @return array<int,int>
     */
    public function countByTypeForHospital(int $hospitalid): array
    {
        return Patient::query()
            ->where('hospitalid', $hospitalid)
            ->selectRaw('isout, COUNT(id) AS total')
            ->groupBy('isout')
            ->pluck('total', 'isout')
            ->all();
    }
}
