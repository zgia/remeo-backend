<?php

declare(strict_types=1);

namespace App\Controller;

use App\Http\Requests\ChangePasswordRequest;
use App\Http\Requests\LoginRequest;
use App\Http\Requests\ResetPasswordRequest;
use App\Http\Requests\SendSmsRequest;
use App\Service\DoctorService;
use App\Service\HospitalService;
use App\Service\UserService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Mews\Captcha\Captcha;

/** Laravel 会话认证入口。 */
class SigninController extends BaseController
{
    public function __construct(private readonly UserService $userService, private readonly DoctorService $doctorService, private readonly HospitalService $hospitalService) {}

    public function captcha(Captcha $captcha): JsonResponse
    {
        $image = $captcha->create('default', true);

        return $this->success('验证码已生成', ['img' => $image['img']])
            ->header('Cache-Control', 'no-store, private');
    }

    public function dologin(LoginRequest $request): JsonResponse
    {
        $credentials = $request->safe()->only(['mobile', 'password']);
        if (! Auth::attempt($credentials)) {
            return $this->failure('手机号或密码错误。', [], 401);
        }

        request()->session()->regenerate();
        request()->session()->put('auth_password', Auth::user()->getAuthPassword());

        return $this->success('登录成功', ['user' => $this->getUser(UserService::user())]);
    }

    public function islogin(): JsonResponse
    {
        return $this->success('欢迎', ['user' => $this->getUser(UserService::user())]);
    }

    public function logout(): JsonResponse
    {
        Auth::logout();

        request()->session()->invalidate();
        request()->session()->regenerateToken();

        return $this->success('已退出登录');
    }

    public function changePassword(ChangePasswordRequest $request): JsonResponse
    {
        $post = $request->validated();
        $this->userService->changePassword($post);

        Auth::user()->refresh();

        request()->session()->put('auth_password', Auth::user()->getAuthPassword());
        request()->session()->regenerate();

        return $this->success('密码修改成功');

    }

    public function sendSmsCode(SendSmsRequest $request): JsonResponse
    {
        $post = $request->validated();
        $this->userService->sendSmsCode($post['mobile']);

        return $this->success('验证码已发送');

    }

    public function resetPassword(ResetPasswordRequest $request): JsonResponse
    {
        $post = $request->validated();
        $this->userService->resetPassword($post['mobile'], $post['smscode']);

        return $this->success('密码已重置');

    }

    /** @param array<string,mixed> $user @return array<string,mixed> */
    private function getUser(array $user): array
    {
        return [
            'mobile'   => $user['mobile'],
            'name'     => $user['realname'],
            'hospital' => $this->hospitalService->getTitle((int) $user['hospitalid']),
            ...$this->doctorService->getPermissions($user),
        ];
    }
}
