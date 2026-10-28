<?php

use App\Http\Controllers\Api\V1\MenuApiController;
use App\Http\Controllers\Api\V1\ReservaPublicaController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function (): void {
    Route::middleware('throttle:menu-publico')
        ->get('/menu/{slug}', [MenuApiController::class, 'show'])
        ->name('api.v1.menu.show');

    // Public reservations from the restaurant site (no session: strict validation, honeypot and rate limits).
    Route::prefix('sitio/{slug}')->name('api.v1.sitio.')->group(function (): void {
        Route::middleware('throttle:menu-publico')->get('disponibilidad', [ReservaPublicaController::class, 'disponibilidad'])->name('disponibilidad');
        Route::middleware('throttle:reservas-publicas')->post('reservas', [ReservaPublicaController::class, 'store'])->name('reservas');
    });

    // The old panel API is gone (docs/integracion-dashboard.md, sección 22). Any method and any path under it answers 410 without
    // sessions, CSRF or throttles, so an old tab with the previous panel.js gets a clear message instead of a 401, 419 or 429.
    Route::match(['GET', 'HEAD', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'], 'panel/{ruta?}', fn () => response()
        ->json(['message' => 'Esta versión del panel ya no existe. Recarga la página.'], 410)
        ->header('Cache-Control', 'no-store'))
        ->where('ruta', '.*')
        ->name('api.v1.panel.retirado');
});
