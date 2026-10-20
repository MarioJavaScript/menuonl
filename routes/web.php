<?php

use App\Http\Controllers\InvitacionAceptarController;
use App\Http\Controllers\PortadaController;
use Illuminate\Support\Facades\Route;

Route::get('/', PortadaController::class)->name('portada');

// Invitations (Mitad A). /m and /q belong to Mitad B.
Route::middleware('throttle:invitaciones')->prefix('invitaciones')->group(function (): void {
    Route::get('/{token}', [InvitacionAceptarController::class, 'mostrar'])->name('invitaciones.mostrar');
    Route::post('/{token}', [InvitacionAceptarController::class, 'aceptar'])->name('invitaciones.aceptar');
});

// Mitad B: Redirección QR, Menú público y Descargas
use App\Http\Controllers\MenuPublicoController;
use App\Http\Controllers\QrDownloadController;
use App\Http\Controllers\QrRedirectController;
use App\Http\Controllers\SitioPrototipoController;

Route::middleware('throttle:menu-publico')->group(function (): void {
    Route::get('/q/{codigo}', [QrRedirectController::class, 'redirigir'])
        ->where('codigo', '.*')
        ->name('qr.redirect');

    Route::get('/m/{slug}', [MenuPublicoController::class, 'mostrar'])
        ->name('menu.publico');
});

Route::middleware('auth')
    ->get('/admin/codigos-qr/{codigoQr}/descargar/{formato}', [QrDownloadController::class, 'descargar'])
    ->where('formato', 'svg|png|pdf')
    ->name('codigos-qr.descargar');

// Sitio completo del restaurante: disponible para todos los restaurantes activos, con el mismo limitador que /m.
Route::middleware('throttle:menu-publico')
    ->get('/sitio/{slug}', [SitioPrototipoController::class, 'mostrar'])
    ->name('sitio.prototipo');
