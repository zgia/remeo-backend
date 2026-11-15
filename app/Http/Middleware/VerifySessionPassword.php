<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * 密码被管理员或短信重置后，已有会话必须重新认证。
 */
class VerifySessionPassword
{
    public function handle(Request $request, Closure $next): Response
    {
        $password = $request->session()->get('auth_password');
        if (! is_string($password) || ! hash_equals($request->user()->getAuthPassword(), $password)) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
            abort(401);
        }

        return $next($request);
    }
}
