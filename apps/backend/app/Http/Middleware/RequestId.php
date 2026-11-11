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

        return $response;
    }
}
