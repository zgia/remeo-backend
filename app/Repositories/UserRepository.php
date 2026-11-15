<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\User;

/**
 * 用户数据仓库
 */
class UserRepository
{
    /**
     * 设置登录信息
     *
     * @param  array<string,mixed>  $user  用户
     */
    public function newLogin(array $user): void
    {
        $this->updateUserVisit($user);
    }

    /**
     * 更新用户的最后访问信息
     *
     * @param  array<string,mixed>  $user  用户信信息
     */
    public function updateUserVisit(array $user): void
    {
        User::query()
            ->where('id', $user['id'])
            ->update(
                [
                    'lastactivity' => timenow(),
                    'lastip'       => request()
                        ->ip(),
                    'lastrealip' => request()
                        ->ip(),
                ]
            );
    }

    /**
     * 新增用户
     *
     * @param  array<string,mixed>  $user  用户信息
     * @return int 用户ID
     */
    public function createUser(array $user)
    {
        return User::query()
            ->insertGetId($user);
    }

    /**
     * 根据条件获取用户信息
     *
     * @param  array<string,mixed>  $cond
     * @return null|array<string,mixed>
     */
    public function getUserByCond(array $cond)
    {
        if (! $cond) {
            return [];
        }

        $user = User::query()
            ->where($cond)
            ->first();

        // 内部密码校验需要 password 和 salt，不能使用会隐藏凭据的 toArray()。
        return $user?->getAttributes();
    }

    /**
     * 根据电子邮件获取用户信息
     *
     * @param  string  $email  电子邮件
     * @return null|array<string,mixed>
     */
    public function getUserByEmail(?string $email)
    {
        return $this->getUserByCond(compact('email'));
    }

    /**
     * 根据手机号获取用户信息
     *
     * @param  string  $mobile  手机号码
     * @return null|array<string,mixed>
     */
    public function getUserByMobile(?string $mobile)
    {
        return $this->getUserByCond(compact('mobile'));
    }

    /**
     * 根据用户名获取用户信息
     *
     * @param  string  $username  用户名
     * @return null|array<string,mixed>
     */
    public function getUserByName(?string $username)
    {
        return $this->getUserByCond(compact('username'));
    }

    /**
     * 根据用户ID获取用户信息
     *
     * @param  int  $userid  用户ID
     * @return array<string,mixed>
     */
    public function getUserById(int $userid)
    {
        $users = $this->getUsersByUserids($userid);

        return $users[$userid] ?? [];
    }

    /**
     * 根据多个用户ID获取多用户信息
     *
     * @param  int|list<int>  $userids  用户ID
     * @return array<array<string,mixed>> 用户信息
     */
    public function getUsersByUserids($userids)
    {
        if (empty($userids)) {
            return [];
        }

        return User::query()
            ->whereIn('id', (array) $userids)
            ->get()
            ->keyBy('id')
            ->map(fn (User $record): array => $record->getAttributes())
            ->all();
    }

    /**
     * 更新用户密码
     *
     * @param  string  $password  经过HASH
     */
    public function updatePassword(int $userid, string $password): void
    {
        User::query()
            ->where('id', $userid)
            ->update(['password' => $password]);
    }

    /**
     * 新用户，ID为0
     *
     * @return array<string,mixed> 用户基础信息
     */
    public static function newUser()
    {
        return ['id' => 0, 'username' => null, 'email' => null];
    }

    /**
     * 空用户
     *
     * @return array<string,mixed> 空数组
     */
    public static function emptyUser()
    {
        return [];
    }
}
