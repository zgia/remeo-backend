<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\Hospital;

class HospitalRepository
{
    public function titleFor(int $hospitalid): ?string
    {
        return Hospital::query()
            ->where('id', $hospitalid)
            ->where('deletedat', 0)
            ->value('title');
    }
}
