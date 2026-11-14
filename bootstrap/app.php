<?php

use App\Exception\BusinessException;
use App\Http\Middleware\LogContext;
use App\Http\Middleware\VerifySessionPassword;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;
use Illuminate\Session\TokenMismatchException;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(web: __DIR__.'/../routes/web.php', commands: __DIR__.'/../routes/console.php', health: '/up')
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->redirectGuestsTo(fn () => null);
        $middleware->alias([
            'session.password' => VerifySessionPassword::class,
        ]);
        $middleware->trimStrings(except: ['password', 'password1', 'password2']);

        $middleware->use([
            LogContext::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(fn (Request $request) => true);
        $exceptions->dontFlash(['password', 'password1', 'password2', 'smscode']);

        $exceptions->render(function (Throwable $e, Request $request) {
            if ($e instanceof HttpResponseException) {
                return $e->getResponse();
            }

            if ($e instanceof BusinessException) {
                if ($e->getPrevious() && ! $e->getPrevious() instanceof BusinessException) {
                    return response()->json(['code' => 1, 'msg' => '请求处理失败，请稍后重试。', 'data' => (object) []], 500);
                }

                return response()->json(['code' => 1, 'msg' => $e->getMessage(), 'data' => $e->getMore()], 418);
            }
            $status = match (true) {
                $e instanceof AuthenticationException => 401,
                $e instanceof ValidationException     => 422,
                $e instanceof TokenMismatchException  => 419,
                $e instanceof HttpExceptionInterface  => $e->getStatusCode(),
                default                               => 500,
            };
            $message = match ($status) {
                401     => '需要重新登录。',
                403     => '没有操作权限。',
                404     => '资源不存在。',
                419     => '会话已过期，请刷新页面后重试。',
                422     => '输入信息有误，请检查。',
                429     => '操作过于频繁，请稍后再试。',
                default => '请求处理失败，请稍后重试。',
            };

            return response()->json(['code' => 1, 'msg' => $message, 'data' => $e instanceof ValidationException ? ['errors' => $e->errors()] : (object) []], $status);
        });
    })->create();
