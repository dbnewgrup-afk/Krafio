<?php

use App\Http\Controllers\SiteController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
 * Rute web Laravel hanya melayani WEBSITE PENGGUNA (SPA Krafio disajikan Nginx dari apps/frontend/dist):
 *  - <subdomain>.<KRAFIO_SITE_DOMAIN>  → website yang dipublikasikan
 *  - domain kustom terverifikasi       → website pemilik domain (dicocokkan dari header Host)
 */
Route::domain('{subdomain}.'.config('krafio.site_domain'))->group(function () {
    Route::get('/', [SiteController::class, 'home']);
    Route::get('/robots.txt', [SiteController::class, 'robots']);
    Route::get('/sitemap.xml', [SiteController::class, 'sitemap']);
});

Route::get('/', function (Request $r) {
    $host = strtolower($r->getHost());
    $app = strtolower((string) parse_url((string) config('app.url'), PHP_URL_HOST));
    // Host aplikasi utama / lokal → halaman bawaan Laravel; host lain → domain kustom website pengguna
    if (in_array($host, [$app, 'localhost', '127.0.0.1'], true)) {
        return view('welcome');
    }

    return app(SiteController::class)->home($r);
});
Route::get('/robots.txt', [SiteController::class, 'robots']);
Route::get('/sitemap.xml', [SiteController::class, 'sitemap']);
