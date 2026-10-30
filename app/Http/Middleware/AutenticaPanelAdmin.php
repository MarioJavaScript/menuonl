<?php

namespace App\Http\Middleware;

use App\Http\Controllers\CuentaPausadaController;
use App\Http\Controllers\PlanSinPanelController;
use App\Models\User;
use Filament\Facades\Filament;
use Filament\Http\Middleware\Authenticate;
use Illuminate\Http\Exceptions\HttpResponseException;

/**
 * La autenticación de Filament, con una excepción: quien no puede entrar a ningún restaurante ve una pantalla
 * explicativa en lugar de un 403 a secas. Dos casos, el primero gana: todos sus restaurantes están pausados (fuera de
 * servicio) o solo pertenece a restaurantes sin panel por su plan. Todo lo demás (invitados, cuentas sin restaurantes,
 * cuentas inactivas) se queda con el comportamiento de Filament. Cerrar sesión sigue funcionando.
 */
class AutenticaPanelAdmin extends Authenticate
{
    /**
     * @param  array<string>  $guards
     */
    protected function authenticate($request, array $guards): void
    {
        $guardia = Filament::auth();
        $usuario = $guardia->user();

        if ($guardia->check() && $usuario instanceof User) {
            $respuesta = match (true) {
                $usuario->debeVerElAvisoDeCuentaPausada() => fn () => app(CuentaPausadaController::class)->respuesta($usuario),
                $usuario->debeVerElAvisoDePlanSinPanel() => fn () => app(PlanSinPanelController::class)->respuesta($usuario),
                default => null,
            };

            if ($respuesta !== null) {
                $this->auth->shouldUse(Filament::getAuthGuard());

                if ($request->routeIs('filament.*.auth.logout')) {
                    return;
                }

                throw new HttpResponseException($respuesta());
            }
        }

        parent::authenticate($request, $guards);
    }
}
