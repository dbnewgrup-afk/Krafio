<?php

namespace App\Http\Middleware;

use App\Support\ApiException;
use Closure;
use Illuminate\Http\Request;

/** Hanya akun berstatus active (bukan admin) yang boleh memakai User Panel API. */
class EnsureActiveUser
{
    public function handle(Request $request, Closure $next)
    {
        $u = $request->user();
        if (! $u || $u->isAdmin()) {
            throw new ApiException('forbidden', 'Akses ditolak.', 403);
        }
        if ($u->status !== 'active') {
            throw new ApiException('account_'.$u->status, 'Status akun belum aktif.', 403, ['status' => $u->status]);
        }

        if ($u->needsContactVerification()) {
            throw new ApiException('contact_verification_required', 'Verifikasi email atau nomor HP terlebih dahulu.', 403);
        }

        return $next($request);
    }
}
