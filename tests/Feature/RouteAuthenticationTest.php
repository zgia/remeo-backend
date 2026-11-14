<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * 遍历真实业务路由，防止新增接口或调整分组时遗漏认证。
 */
class RouteAuthenticationTest extends TestCase
{
    private const array PUBLIC_ROUTES = [
        '/', 'index', 'signin/csrf', 'signin/logout', 'signin/dologin',
        'signin/sendsmscode', 'signin/resetpassword', 'signin/captcha',
    ];

    public function test_guests_are_rejected_by_every_protected_business_route(): void
    {
        $checked = 0;

        foreach (Route::getRoutes() as $route) {
            if (str_starts_with(ltrim($route->getActionName(), '\\'), 'Mews\\Captcha\\')) {
                continue;
            }
            if (! in_array('web', $route->gatherMiddleware(), true)
                || in_array($route->uri(), self::PUBLIC_ROUTES, true)) {
                continue;
            }

            foreach (array_diff($route->methods(), ['HEAD']) as $method) {
                $this->json($method, '/'.ltrim($route->uri(), '/'))
                    ->assertStatus(401)
                    ->assertJsonPath('code', 1)
                    ->assertJsonPath('msg', '需要重新登录。');
                $checked++;
            }
        }

        $this->assertGreaterThan(0, $checked);
    }

    public function test_public_entry_points_remain_accessible_without_a_session(): void
    {
        foreach (['/', '/index', '/signin/csrf', '/signin/captcha'] as $uri) {
            $this->getJson($uri)->assertOk()->assertJsonPath('code', 0);
        }

        // 空参数只触发表单验证，不投递短信或重置真实账户密码。
        $this->postJson('/signin/dologin')->assertStatus(422);
        $this->getJson('/signin/sendsmscode')->assertStatus(422);
        $this->postJson('/signin/resetpassword')->assertStatus(422);
        $this->postJson('/signin/logout')->assertOk()->assertJsonPath('code', 0);
        $this->postJson('/signin/logout')->assertOk()->assertJsonPath('code', 0);
    }

    public function test_stale_password_sessions_are_rejected_before_business_handlers(): void
    {
        $user = new User;
        $user->forceFill(['id' => 1, 'password' => 'updated-password-hash']);

        foreach (['/category', '/operator', '/globaloptions', '/hospital/items', '/doctor/items', '/outpatient/items', '/inhospital/items', '/signin/islogin'] as $uri) {
            $this->actingAs($user)->withSession(['auth_password' => 'old-password-hash']);
            $this->getJson($uri)->assertStatus(401)->assertJsonPath('code', 1);
            $this->assertGuest();
            $this->assertFalse(session()->has('auth_password'));
        }
    }
}
