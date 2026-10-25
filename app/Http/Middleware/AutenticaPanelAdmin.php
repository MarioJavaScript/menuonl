<?php

namespace App\Http\Middleware;

use App\Http\Controllers\PlanSinPanelController;
use App\Models\User;
use Filament\Facades\Filament;
use Filament\Http\Middleware\Authenticate;
use Illuminate\Http\Exceptions\HttpResponseException;

/**
 * La autenticación de Filament, con una excepción: quien solo pertenece a restaurantes sin panel por su plan
 * ve la pantalla explicativa en lugar de un 403 a secas. Todo lo demás (invitados, cuentas sin restaurantes,
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

        if ($guardia->check() && $usuario instanceof User && $usuario->debeVerElAvisoDePlanSinPanel()) {
            $this->auth->shouldUse(Filament::getAuthGuard());

            if ($request->routeIs('filament.*.auth.logout')) {
                return;
            }

            throw new HttpResponseException(app(PlanSinPanelController::class)->respuesta($usuario));
        }

        parent::authenticate($request, $guards);
    }
}
