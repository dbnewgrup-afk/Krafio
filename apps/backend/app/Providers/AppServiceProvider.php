<?php

namespace App\Providers;

use App\Services\JualKirim\JualKirimClient;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Integrasi JualKirim API v1 (docs/JUALKIRIM.md) — satu instance per proses
        $this->app->singleton(JualKirimClient::class, fn ($app) => new JualKirimClient($app['config']->get('jualkirim')));
    }

    public function boot(): void
    {
        // Tautan reset password diarahkan ke SPA (Design Spec §9.6)
        ResetPassword::createUrlUsing(fn ($user, string $token) => rtrim(config('krafio.frontend_url'), '/').'/reset-password?token='.$token.'&email='.urlencode($user->getEmailForPasswordReset()));
    }
}
