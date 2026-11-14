<?php

namespace App\Providers;

use App\Services\JualKirim\JualKirimClient;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
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

        // Batas request API global: per user login, atau per IP untuk tamu. Webhook gateway & berkas media dikecualikan
        // (wajar banyak request); batas khusus per rute (throttle:...) tetap berlaku di atasnya.
        RateLimiter::for('api', function (Request $r) {
            if ($r->is('api/webhooks/*') || $r->is('api/media/*')) {
                return Limit::none();
            }

            return $r->user()
                ? Limit::perMinute(max(10, (int) config('krafio.api_rate_user', 300)))->by('u:'.$r->user()->id)
                : Limit::perMinute(max(10, (int) config('krafio.api_rate_guest', 120)))->by('ip:'.$r->ip());
        });
    }
}
