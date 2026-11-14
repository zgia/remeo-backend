<?php

declare(strict_types=1);

namespace App\Providers;

use App\Auth\LegacyUserProvider;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

/** 应用服务注册。 */
class AppServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Gate::define('manage-administration', fn () => isOperator());
        Gate::define('download-patient', fn () => canDownload());
        Auth::provider('legacy-eloquent', fn ($app, array $config) => new LegacyUserProvider($app['hash'], $config['model']));

        Log::shareContext(['request_id' => (string) Str::uuid()]);
    }
}
