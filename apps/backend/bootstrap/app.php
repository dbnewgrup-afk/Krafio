<?php

use App\Http\Middleware\EnsureActiveUser;
use App\Http\Middleware\EnsureAdmin;
use App\Http\Middleware\RequestId;
use App\Support\ApiException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\Request;
use Illuminate\Session\TokenMismatchException;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->statefulApi();
        $middleware->prepend(RequestId::class);
        $middleware->alias(['active' => EnsureActiveUser::class, 'admin' => EnsureAdmin::class]);
        $middleware->trustProxies(at: '*');
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(fn (Request $request) => $request->is('api/*') || $request->expectsJson());

        // Format error standar {code, message, errors, request_id} — tanpa stack trace/SQL (Design Spec §17.4)
        $json = fn (Request $r, string $code, string $msg, int $status, array $errors = [], array $extra = []) => response()->json(array_merge(
            ['code' => $code, 'message' => $msg, 'errors' => (object) $errors, 'request_id' => $r->attributes->get('request_id')],
            $extra,
        ), $status);

        $exceptions->render(fn (ValidationException $e, Request $r) => $r->is('api/*') ? $json($r, 'validation_failed', 'Data yang dikirim belum valid.', 422, $e->errors()) : null);
        $exceptions->render(fn (AuthenticationException $e, Request $r) => $r->is('api/*') ? $json($r, 'unauthenticated', 'Silakan masuk.', 401) : null);
        $exceptions->render(fn (ThrottleRequestsException $e, Request $r) => $r->is('api/*') ? $json($r, 'too_many_attempts', 'Terlalu banyak permintaan.', 429, [], ['retry_after' => (int) ($e->getHeaders()['Retry-After'] ?? 60)]) : null);
        $exceptions->render(fn (TokenMismatchException $e, Request $r) => $r->is('api/*') ? $json($r, 'csrf_mismatch', 'Sesi keamanan kedaluwarsa. Muat ulang halaman.', 419) : null);
        $exceptions->render(fn (NotFoundHttpException $e, Request $r) => $r->is('api/*') ? $json($r, 'not_found', 'Data tidak ditemukan.', 404) : null);
        $exceptions->render(fn (ModelNotFoundException $e, Request $r) => $r->is('api/*') ? $json($r, 'not_found', 'Data tidak ditemukan.', 404) : null);
        $exceptions->render(function (ApiException $e, Request $r) {
            $res = $e->render();
            $data = $res->getData(true);
            $data['request_id'] = $r->attributes->get('request_id');

            return $res->setData($data);
        });
        $exceptions->render(function (\Throwable $e, Request $r) use ($json) {
            if (! $r->is('api/*')) {
                return null;
            }
            if ($e instanceof HttpExceptionInterface) {
                return $json($r, $e->getStatusCode() === 403 ? 'forbidden' : 'http_error', $e->getStatusCode() === 403 ? 'Akses ditolak.' : 'Permintaan tidak dapat diproses.', $e->getStatusCode());
            }
            report($e);

            return $json($r, 'server_error', 'Terjadi kesalahan pada server.', 500);
        });
    })->create();
