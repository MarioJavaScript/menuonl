<?php

use App\Http\Controllers\Api\V1\MenuApiController;
use App\Http\Controllers\Api\V1\Panel\AjustesController;
use App\Http\Controllers\Api\V1\Panel\EquipoController;
use App\Http\Controllers\Api\V1\Panel\ImagenController;
use App\Http\Controllers\Api\V1\Panel\MenuController;
use App\Http\Controllers\Api\V1\Panel\ReservaController;
use App\Http\Controllers\Api\V1\Panel\ResumenController;
use App\Http\Controllers\Api\V1\ReservaPublicaController;
use App\Http\Middleware\ContextoRestaurantePanel;
use App\Http\Middleware\ModuloPanel;
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

    // Admin panel API: same session as the Filament login (cookie + CSRF), scoped to one restaurant.
    Route::middleware(['web', 'auth', 'throttle:panel-api'])
        ->get('panel', [ResumenController::class, 'restaurantes'])
        ->name('api.v1.panel.restaurantes');

    Route::middleware(['web', 'auth', 'throttle:panel-api', ContextoRestaurantePanel::class])
        ->prefix('panel/{restaurante:slug}')
        ->name('api.v1.panel.')
        ->group(function (): void {
            Route::middleware(ModuloPanel::class.':inicio')->get('resumen', ResumenController::class)->name('resumen');

            Route::middleware(ModuloPanel::class.':reservas')->group(function (): void {
                Route::apiResource('reservas', ReservaController::class)->except('show');
            });

            Route::middleware(ModuloPanel::class.':menu')->group(function (): void {
                Route::get('menu', [MenuController::class, 'index'])->name('menu.index');
                Route::post('categorias/{categoria}/productos', [MenuController::class, 'store'])->name('productos.store');
                Route::patch('productos/{producto}', [MenuController::class, 'update'])->name('productos.update');
                Route::post('productos/{producto}/duplicar', [MenuController::class, 'duplicar'])->name('productos.duplicar');
                Route::delete('productos/{producto}', [MenuController::class, 'destroy'])->name('productos.destroy');
                Route::post('productos/{producto}/imagen', [ImagenController::class, 'producto'])->name('productos.imagen');
                Route::delete('productos/{producto}/imagen', [ImagenController::class, 'quitarProducto'])->name('productos.imagen.quitar');
            });

            Route::middleware(ModuloPanel::class.':ajustes')->group(function (): void {
                Route::get('ajustes', [AjustesController::class, 'show'])->name('ajustes.show');
                Route::patch('ajustes', [AjustesController::class, 'update'])->name('ajustes.update');
                Route::post('ajustes/hero', [ImagenController::class, 'hero'])->name('ajustes.hero');
                Route::delete('ajustes/hero', [ImagenController::class, 'quitarHero'])->name('ajustes.hero.quitar');
                Route::post('ajustes/galeria', [ImagenController::class, 'galeria'])->name('ajustes.galeria');
                Route::delete('ajustes/galeria/{imagen}', [ImagenController::class, 'quitarGaleria'])->name('ajustes.galeria.quitar');
                Route::put('ajustes/horarios/{dia}', [AjustesController::class, 'horario'])->whereNumber('dia')->name('ajustes.horario');
            });

            Route::middleware(ModuloPanel::class.':ajustes')->prefix('equipo')->name('equipo.')->group(function (): void {
                Route::get('/', [EquipoController::class, 'index'])->name('index');
                Route::post('invitaciones', [EquipoController::class, 'invitar'])->name('invitar');
                Route::delete('invitaciones/{invitacion}', [EquipoController::class, 'revocar'])->name('revocar');
                Route::patch('usuarios/{usuario}', [EquipoController::class, 'update'])->name('update');
            });
        });
});
