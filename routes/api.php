<?php

use App\Http\Controllers\Api\V1\MenuApiController;
use App\Http\Controllers\Api\V1\Panel\EquipoController;
use App\Http\Controllers\Api\V1\Panel\MenuController;
use App\Http\Controllers\Api\V1\Panel\PedidoController;
use App\Http\Controllers\Api\V1\Panel\ReservaController;
use App\Http\Controllers\Api\V1\Panel\ResumenController;
use App\Http\Middleware\ContextoRestaurantePanel;
use App\Http\Middleware\ModuloPanel;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function (): void {
    Route::middleware('throttle:menu-publico')
        ->get('/menu/{slug}', [MenuApiController::class, 'show'])
        ->name('api.v1.menu.show');

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

            Route::middleware(ModuloPanel::class.':pedidos')->group(function (): void {
                Route::get('pedidos', [PedidoController::class, 'index'])->name('pedidos.index');
                Route::post('pedidos', [PedidoController::class, 'store'])->name('pedidos.store');
                Route::post('pedidos/{pedido}/avanzar', [PedidoController::class, 'avanzar'])->name('pedidos.avanzar');
            });

            Route::middleware(ModuloPanel::class.':menu')->group(function (): void {
                Route::get('menu', [MenuController::class, 'index'])->name('menu.index');
                Route::post('categorias/{categoria}/productos', [MenuController::class, 'store'])->name('productos.store');
                Route::patch('productos/{producto}', [MenuController::class, 'update'])->name('productos.update');
                Route::post('productos/{producto}/duplicar', [MenuController::class, 'duplicar'])->name('productos.duplicar');
                Route::delete('productos/{producto}', [MenuController::class, 'destroy'])->name('productos.destroy');
            });

            Route::middleware(ModuloPanel::class.':ajustes')->prefix('equipo')->name('equipo.')->group(function (): void {
                Route::get('/', [EquipoController::class, 'index'])->name('index');
                Route::post('invitaciones', [EquipoController::class, 'invitar'])->name('invitar');
                Route::delete('invitaciones/{invitacion}', [EquipoController::class, 'revocar'])->name('revocar');
                Route::patch('usuarios/{usuario}', [EquipoController::class, 'update'])->name('update');
            });
        });
});
