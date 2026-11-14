<?php

declare(strict_types=1);

namespace App\Auth;

use App\Models\User;
use Illuminate\Auth\EloquentUserProvider;
use Illuminate\Contracts\Auth\Authenticatable;

/** 旧账户验证通过后，SessionGuard 自动将密码升级为 Laravel 散列。 */
class LegacyUserProvider extends EloquentUserProvider
{
    /** @param Authenticatable $user @param array<string,mixed> $credentials @return bool */
    public function validateCredentials(Authenticatable $user, array $credentials)
    {
        if (! $user instanceof User) {
            return false;
        }
        $hash = $user->getAuthPassword();
        if (preg_match('/^[a-f0-9]{32}$/i', $hash)) {
            return hash_equals($hash, md5(md5($credentials['password']).$user->salt));
        }

        return parent::validateCredentials($user, $credentials);
    }
}
