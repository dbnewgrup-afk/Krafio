<?php

namespace App\Http\Middleware;

use App\Services\Settings;
use App\Support\ApiException;
use Closure;
use Illuminate\Http\Request;

/** Admin Panel: role admin + MFA (TOTP) wajib lolos pada sesi ini + timeout sesi idle (Design Spec §7.2). */
class EnsureAdmin
{
    public function handle(Request $request, Closure $next)
    {
        $u = $request->user();
        if (! $u || ! $u->isAdmin()) {
            throw new ApiException('forbidden', 'Akses ditolak.', 403);
        }
        if (! session('admin_mfa_passed')) {
            throw new ApiException('mfa_required', 'Verifikasi MFA diperlukan.', 403);
        }
        $limit = (int) Settings::get('security.admin_session_minutes') * 60;
        $last = (int) session('admin_last_seen', time());
        if (time() - $last > $limit) {
            session()->forget('admin_mfa_passed');
            throw new ApiException('session_expired', 'Sesi admin berakhir.', 401);
        }
        if (! $request->is('api/admin/session/ping') || $request->isMethod('post')) {
            session(['admin_last_seen' => time()]);
        }

        return $next($request);
    }
}
