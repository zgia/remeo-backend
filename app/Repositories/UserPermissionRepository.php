<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\UserPermission;

class UserPermissionRepository
{
    /**
     * @return list<string>
     */
    public function codesForDoctor(int $doctorid): array
    {
        return UserPermission::query()
            ->where('doctorid', $doctorid)
            ->pluck('permissioncode')
            ->all();
    }
}
