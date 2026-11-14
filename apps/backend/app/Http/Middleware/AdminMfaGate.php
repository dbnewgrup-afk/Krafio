<?php

namespace App\Http\Middleware;

use App\Support\ApiException;
use Closure;
use Illuminate\Http\Request;

/**
 * B8 (audit 14 Nov 2026): admin yang baru memasukkan kata sandi tapi belum lolos MFA sudah punya sesi login.
 * Sebelum MFA lolos, sesi itu hanya boleh membaca profil (GET /me) — tidak boleh ganti kata sandi, cabut sesi lain, dll.
 * Rute /api/admin/* sudah dijaga EnsureAdmin; /api/auth/* (verifikasi MFA, logout) tidak melewati middleware ini.
 */
class AdminMfaGate
{
    public function handle(Request $request, Closure $next)
    {
        $u = $request->user();
        if ($u && $u->isAdmin() && ! session('admin_mfa_passed') && ! ($request->isMethod('GET') && $request->is('api/me'))) {
            throw new ApiException('mfa_required', 'Verifikasi MFA diperlukan.', 403);
        }

        return $next($request);
    }
}
