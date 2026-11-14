<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/** ID permintaan untuk korelasi log & tampilan error (Design Spec §17.4). */
class RequestId
{
    public function handle(Request $request, Closure $next)
    {
        $id = $request->header('X-Request-Id') ?: (string) Str::ulid();
        $request->attributes->set('request_id', $id);
        $response = $next($request);
        $response->headers->set('X-Request-Id', $id);
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('X-Frame-Options', 'DENY');
        // B7 (audit 14 Nov 2026): header keamanan tambahan untuk respons API
        $response->headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=(), payment=()');
        if (! $response->headers->has('Content-Security-Policy')) {
            $response->headers->set('Content-Security-Policy', "default-src 'none'; frame-ancestors 'none'; base-uri 'none'");
        }
        if ($request->isSecure()) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }
        // Jangan bocorkan versi PHP (ditambahkan PHP sendiri bila expose_php = On)
        $response->headers->remove('X-Powered-By');
        if (! headers_sent()) {
            header_remove('X-Powered-By');
        }

        return $response;
    }
}
