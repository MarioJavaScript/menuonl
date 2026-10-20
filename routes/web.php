<?php

use App\Http\Controllers\InvitacionAceptarController;
use App\Http\Controllers\PortadaController;
use Illuminate\Support\Facades\Route;

Route::get('/', PortadaController::class)->name('portada');

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

Route::middleware('throttle:menu-publico')->group(function (): void {
    Route::get('/q/{codigo}', [QrRedirectController::class, 'redirigir'])
        ->where('codigo', '.*')
        ->name('qr.redirect');

    // Old addresses: permanent redirect to the clean ones, keeping ?mesa= and ?lang=.
    Route::get('/m/{slug}', [RutaAnteriorController::class, 'menu'])
        ->where('slug', Catalogos::patronSlugRuta());

    Route::get('/s/{slug}', [RutaAnteriorController::class, 'sitio'])
        ->where('slug', Catalogos::patronSlugRuta());
});

// Service panel (reservations, orders, menu): Blade shell + Vite assets that talk to /api/v1/panel with the login session.
Route::middleware('auth')
    ->get('/panel', fn () => response()->view('panel.index')->header('Cache-Control', 'no-store'))
    ->name('panel');

Route::middleware('auth')
    ->get('/admin/codigos-qr/{codigoQr}/descargar/{formato}', [QrDownloadController::class, 'descargar'])
    ->where('formato', 'svg|png|pdf')
    ->name('codigos-qr.descargar');

// Public restaurant pages. They MUST stay last: /{slug} is a catch-all for one segment and is kept away from
// the application's own prefixes by the reserved slugs (Catalogos::slugsReservados()).
Route::middleware('throttle:menu-publico')->group(function (): void {
    Route::get('/{slug}/menu', [MenuPublicoController::class, 'mostrar'])
        ->where('slug', Catalogos::patronSlugRuta())
        ->name('menu.publico');

    Route::get('/{slug}', [SitioPublicoController::class, 'mostrar'])
        ->where('slug', Catalogos::patronSlugRuta())
        ->name('sitio.publico');
});
