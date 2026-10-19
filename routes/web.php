<?php

use App\Http\Controllers\InvitacionAceptarController;
use App\Http\Controllers\PortadaController;
use Illuminate\Support\Facades\Route;

Route::get('/', PortadaController::class)->name('portada');

// Design system sample (DISEÑO.md): local environment only.
if (app()->isLocal()) {
    Route::view('/diseno', 'diseno.componentes')->name('diseno');
}

// Invitations (Mitad A). /m and /q belong to Mitad B.
Route::middleware('throttle:invitaciones')->prefix('invitaciones')->group(function (): void {
    Route::get('/{token}', [InvitacionAceptarController::class, 'mostrar'])->name('invitaciones.mostrar');
    Route::post('/{token}', [InvitacionAceptarController::class, 'aceptar'])->name('invitaciones.aceptar');
});

// Mitad B: Redirección QR, Menú público y Descargas
use App\Http\Controllers\MenuPublicoController;
use App\Http\Controllers\QrDownloadController;
use App\Http\Controllers\QrRedirectController;

Route::middleware('throttle:menu-publico')->group(function (): void {
    Route::get('/q/{codigo}', [QrRedirectController::class, 'redirigir'])
        ->where('codigo', '.*')
        ->name('qr.redirect');

    Route::get('/m/{slug}', [MenuPublicoController::class, 'mostrar'])
        ->name('menu.publico');
});

// Service panel (reservations, orders, menu): static page that talks to /api/v1/panel with the login session.
Route::middleware('auth')
    ->get('/panel', fn () => response()->file(public_path('prototipo/panel.html'), ['Cache-Control' => 'no-store']))
    ->name('panel');

Route::middleware('auth')
    ->get('/admin/codigos-qr/{codigoQr}/descargar/{formato}', [QrDownloadController::class, 'descargar'])
    ->where('formato', 'svg|png|pdf')
    ->name('codigos-qr.descargar');
