<?php

use App\Http\Controllers\InvitacionAceptarController;
use App\Http\Controllers\PaginaLegalController;
use App\Http\Controllers\PortadaController;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Route;
use Illuminate\View\Middleware\ShareErrorsFromSession;

// Design system sample (DISEÑO.md): local environment only.
if (app()->isLocal()) {
    Route::view('/diseno', 'diseno.componentes')->name('diseno');
}

// Invitations (Mitad A). /q and the restaurant pages belong to Mitad B.
Route::middleware('throttle:invitaciones')->prefix('invitaciones')->group(function (): void {
    Route::get('/{token}', [InvitacionAceptarController::class, 'mostrar'])->name('invitaciones.mostrar');
    Route::post('/{token}', [InvitacionAceptarController::class, 'aceptar'])->name('invitaciones.aceptar');
});

// Mitad B: Redirección QR, Menú público y Descargas
use App\Http\Controllers\MenuPublicoController;
use App\Http\Controllers\QrDownloadController;
use App\Http\Controllers\QrRedirectController;
use App\Http\Controllers\RutaAnteriorController;
use App\Http\Controllers\SitioPublicoController;
use App\Services\Catalogos;

// Public pages are stateless: no session, cookies or CSRF token, so Cloudflare can cache them (docs/despliegue.md, H1).
// Language comes from ?lang= and the table from ?mesa=; reservations and orders use the stateless /api/v1 routes.
$middlewareDeSesion = [
    EncryptCookies::class,
    AddQueuedCookiesToResponse::class,
    StartSession::class,
    ShareErrorsFromSession::class,
    PreventRequestForgery::class,
];

Route::middleware('throttle:menu-publico')->withoutMiddleware($middlewareDeSesion)->group(function (): void {
    // Portada y páginas legales: estáticas para el visitante, cacheables (Cache-Control lo pone cada controlador).
    Route::get('/', PortadaController::class)->name('portada');
    Route::get('/privacidad', [PaginaLegalController::class, 'privacidad'])->name('privacidad');
    Route::get('/terminos', [PaginaLegalController::class, 'terminos'])->name('terminos');

    Route::get('/q/{codigo}', [QrRedirectController::class, 'redirigir'])
        ->where('codigo', '.*')
        ->name('qr.redirect');

    // Old addresses: permanent redirect to the clean ones, keeping ?mesa= and ?lang=.
    Route::get('/m/{slug}', [RutaAnteriorController::class, 'menu'])
        ->where('slug', Catalogos::patronSlugRuta());

    Route::get('/s/{slug}', [RutaAnteriorController::class, 'sitio'])
        ->where('slug', Catalogos::patronSlugRuta());
});

// The old service panel is gone: /panel and everything under it send people to /admin (docs/integracion-dashboard.md, sección 22).
// No session, cookies or CSRF here, so a stale form POST gets the redirect instead of a 419, and nobody sees a login first:
// /admin decides (login, "sin panel" notice or the dashboard). 302 and no-store: the door must never be cached for good.
Route::withoutMiddleware($middlewareDeSesion)
    ->match(['GET', 'HEAD', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'], '/panel/{ruta?}', fn () => redirect('/admin', 302)->header('Cache-Control', 'no-store'))
    ->where('ruta', '.*')
    ->name('panel');

Route::middleware('auth')
    ->get('/admin/codigos-qr/{codigoQr}/descargar/{formato}', [QrDownloadController::class, 'descargar'])
    ->where('formato', 'svg|png|pdf')
    ->name('codigos-qr.descargar');

// Public restaurant pages. They MUST stay last: /{slug} is a catch-all for one segment and is kept away from
// the application's own prefixes by the reserved slugs (Catalogos::slugsReservados()).
Route::middleware('throttle:menu-publico')->withoutMiddleware($middlewareDeSesion)->group(function (): void {
    Route::get('/{slug}/menu', [MenuPublicoController::class, 'mostrar'])
        ->where('slug', Catalogos::patronSlugRuta())
        ->name('menu.publico');

    Route::get('/{slug}', [SitioPublicoController::class, 'mostrar'])
        ->where('slug', Catalogos::patronSlugRuta())
        ->name('sitio.publico');
});
