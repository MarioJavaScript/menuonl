<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\ContactoSoporte;
use Filament\Facades\Filament;
use Illuminate\Http\Response;

/**
 * Pantalla para quien solo pertenece a restaurantes cuyo plan no incluye el panel (docs/planes.md, D10).
 * Responde 403 (sigue sin haber acceso) pero con un mensaje claro en lugar de un error a secas. Solo muestra el
 * nombre del restaurante: nunca el plan, la dirección corta ni ningún otro dato interno.
 */
class PlanSinPanelController extends Controller
{
    public function __construct(private readonly ContactoSoporte $soporte) {}

    public function respuesta(User $usuario): Response
    {
        return response()
            ->view('plan.sin-panel', [
                'restaurantes' => $usuario->restaurantesSinPanelPorPlan()->pluck('nombre')->all(),
                'enlaceWhatsapp' => $this->soporte->enlaceWhatsapp(),
                'urlCerrarSesion' => Filament::getCurrentOrDefaultPanel()->getLogoutUrl(),
            ], 403)
            ->header('Cache-Control', 'no-store')
            ->header('X-Robots-Tag', 'noindex');
    }
}
