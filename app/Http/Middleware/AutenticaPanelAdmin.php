<?php

namespace App\Http\Middleware;

use App\Http\Controllers\CuentaPausadaController;
use App\Models\User;
use Filament\Facades\Filament;
use Filament\Http\Middleware\Authenticate;
use Illuminate\Http\Exceptions\HttpResponseException;

/**
 * La autenticación de Filament, con una excepción: quien no puede entrar a ningún restaurante porque todos los suyos
 * están pausados (fuera de servicio) ve una pantalla explicativa en lugar de un 403 a secas. Todo lo demás (invitados,
 * cuentas sin restaurantes, cuentas inactivas) se queda con el comportamiento de Filament. Cerrar sesión sigue
 * funcionando. Los dos planes (Aura y Lite) incluyen el panel, así que el plan nunca deja a nadie afuera.
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
            if ($usuario->debeVerElAvisoDeCuentaPausada()) {
                $this->auth->shouldUse(Filament::getAuthGuard());

                if ($request->routeIs('filament.*.auth.logout')) {
                    return;
                }

                throw new HttpResponseException(app(CuentaPausadaController::class)->respuesta($usuario));
            }
        }

        parent::authenticate($request, $guards);
    }
}
