<?php

namespace App\Service;

use App\Exception\UserException;
use App\Helper\AliyunSmsHelper;
use App\Models\SmsCode;
use App\Models\SmsLog;
use App\Models\User;
use App\Repositories\LogRepository;
use App\Repositories\UserRepository;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Class UserService
 */
class UserService
{
    public function __construct(
        protected readonly UserRepository $userRepository,
        protected readonly LogRepository $logRepository,
        protected readonly AliyunSmsHelper $sms,
    ) {}

    /**
     * 获取当前的用户信息
     *
     * @return array<string,mixed>
     */
    public static function user()
    {
        return Auth::user()?->getAttributes() ?? [];
    }

    /**
     * 获取用户信息
     *
     * @param  int  $userid  用户ID
     * @return array<string,mixed>
     */
    public function getUser($userid)
    {
        $userid = (int) $userid;

        return $userid ? $this->userRepository->getUserById($userid) : static::emptyUser();
    }

    /**
     * 创建新用户/编辑用户
     *
     * @param  array<string,mixed>  $user  用户信息
     * @return int 用户ID
     */
    public function saveUser($user)
    {
        $userid = intval($user['id'] ?? '');
        unset($user['id']);

        // 检查当前用户是否存在
        $editUserInfo = [];
        if ($userid) {
            $editUserInfo = $this->getUser($userid);
            if (empty($editUserInfo['id'])) {
                throw new UserException(__('The user is not existed.'));
            }
        }

        $unique = $this->isUniqueMobile($user['mobile'], $userid);
        if (! $unique) {
            throw new UserException('已经有相同手机号的医生存在了。');
        }

        if ($userid) {
            $user['updated_at'] = now();
            User::query()->where('user.id', $userid)->update($user);
        } else {
            // 新建用户时，密码默认为手机号的最后7位
            $user['salt']     = '';
            $user['password'] = static::getInitPassword($user['mobile'], $user['salt']);

            $user['created_at'] = now();
            $user['updated_at'] = now();
            $userid             = $this->userRepository->createUser($user);
        }

        // 保存日志
        $log = [
            'type'   => 'user',
            'action' => 'update',
            'id'     => $userid,
            'from'   => array_diff_key($editUserInfo, array_flip(['password', 'salt'])),
            'to'     => array_diff_key($user, array_flip(['password', 'salt'])),
        ];
        $this->logRepository->record($log);

        return $userid;
    }

    /**
     * 初始密码为手机号后7位
     *
     *
     * @return string
     */
    public static function getInitPassword(string $mobile, string $salt)
    {
        return static::genPassword(substr($mobile, -7), $salt);
    }

    /**
     * 通过短信重置密码，短信验证码的有效时间是15分钟
     */
    public function resetPassword(string $mobile, string $code): void
    {
        $smscode = SmsCode::query()
            ->where('smscode.mobile', $mobile)
            ->where('smscode.smscode', $code)
            ->where('smscode.created_at', '>', now()->subSeconds((int) getOption('smslife', 900)))
            ->select('smscode.*')
            ->first();
        $smscode = $smscode?->getAttributes();

        if ($smscode) {
            $password = Str::random(12);

            $data             = ['salt' => '', 'updated_at' => now()];
            $data['password'] = static::genPassword($password, $data['salt']);

            User::query()->where('user.id', $smscode['userid'])->update($data);

            SmsCode::query()->where('smscode.id', $smscode['id'])->delete();
            $this->sendSMSToUser(['id' => $smscode['userid'], 'mobile' => $mobile], $password, 'pwd');
        } else {
            throw new UserException('验证码不存在或者过期，请重试。');
        }
    }

    /**
     * 发送验证码
     */
    public function sendSmsCode(string $mobile): void
    {
        $user = $this->userRepository->getUserByMobile($mobile);
        if (empty($user)) {
            throw new UserException('错误的手机号，请检查后重试。');
        }

        $total       = SmsCode::query()->where('smscode.userid', $user['id'])->where('smscode.smsday', formatDate())->count();
        $smsdaytotal = getOption('smsdaytotal', 10);
        if ($total > $smsdaytotal) {
            throw new UserException('每天不能发送超过'.$smsdaytotal.'次短信。如果事情紧急，可以联系管理员解决。');
        }

        $smscode = (string) random_int(100000, 999999);

        SmsCode::query()->insert([
            'userid'     => $user['id'],
            'mobile'     => $user['mobile'],
            'smscode'    => $smscode,
            'created_at' => now(),
            'updated_at' => now(),
            'smsday'     => formatDate(),
        ]);

        $this->sendSMSToUser($user, $smscode, 'code');
    }

    /**
     * @param  array<string,mixed>  $user
     */
    public function sendSMSToUser(array $user, string $code, string $type): void
    {
        SmsLog::query()->insert([
            'userid'     => $user['id'],
            'mobile'     => $user['mobile'],
            'msg'        => json_encode(['type' => $type], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->sms->sendSMS($user['mobile'], $code, $type);
    }

    /**
     * 修改密码
     *
     * @param  array<string,mixed>  $post
     */
    public function changePassword(array $post): void
    {
        $user = self::user();
        if (! Hash::check($post['password'], $user['password'])) {
            throw new UserException('错误的旧密码，请检查后重新输入。');
        }

        if (strlen($post['password1']) < getOption('pwdminlen')) {
            throw new UserException('密码至少'.getOption('pwdminlen').'个字符，建议输入数字、大小写混合的字母和特殊字符。');
        }

        if ($post['password1'] != $post['password2']) {
            throw new UserException('两个密码不匹配，请检查后重新输入。');
        }

        $this->userRepository->updatePassword($user['id'], static::genPassword($post['password1'], $user['salt']));
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

    /**
     * @param  array<string,mixed>  $cond
     * @return bool
     */
    public function isUnique(array $cond, int $userid = 0)
    {
        $user = $this->userRepository->getUserByCond($cond);

        // 查无此人
        if (! $user || ! $user['id']) {
            return true;
        }

        // 如果有用户，则检查是否用户自己
        if ($userid && $user['id'] == $userid) {
            return true;
        }

        return false;
    }

    /**
     * 修改账户信息时，检查用户输入的用户名是否唯一。
     *
     * @param  string  $username  用户名
     * @param  int  $userid  用户ID
     * @return bool true表示唯一
     */
    public function isUniqueUsername(string $username, int $userid = 0)
    {
        return $this->isUnique(compact('username'), $userid);
    }

    /**
     * 注册手机用户时，检查用户输入的手机号是否唯一。
     *
     * @param  string  $mobile  mobile
     * @param  int  $userid  用户ID
     * @return bool true表示唯一
     */
    public function isUniqueMobile(string $mobile, int $userid = 0)
    {
        return $this->isUnique(compact('mobile'), $userid);
    }

    /**
     * 生成用户的密码
     *
     * @param  string  $src  用户输入的原始串
     * @param  string  $salt  加密盐
     * @return string 计算后得到的密码
     */
    public static function genPassword(string $src, string $salt)
    {
        return Hash::make($src);
    }
}
