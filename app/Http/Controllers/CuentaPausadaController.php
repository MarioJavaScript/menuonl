<?php

namespace App\Http\Controllers;

use App\Models\Restaurante;
use App\Models\User;
use App\Services\ContactoSoporte;
use Filament\Facades\Filament;
use Illuminate\Http\Response;

/**
 * Aviso para quien solo pertenece a restaurantes pausados (hoy: fuera de servicio). Es el único lugar que lo arma:
 * el texto sale de panel.cuenta_pausada.motivos.{motivo}, así que un motivo nuevo solo suma su entrada en el idioma.
 * Responde 403 (sigue sin haber acceso). Solo muestra el nombre del restaurante: nunca causas, plan ni datos internos.
 */
class CuentaPausadaController extends Controller
{
    public function __construct(private readonly ContactoSoporte $soporte) {}

    public function respuesta(User $usuario): Response
    {
        $pausados = $usuario->restaurantesPausados();

        return response()
            ->view('cuenta.pausada', [
                'restaurantes' => $pausados->pluck('nombre')->all(),
                'mensajes' => $pausados
                    ->map(fn (Restaurante $restaurante): string => __('panel.cuenta_pausada.motivos.'.$restaurante->motivoDePausa()?->value))
                    ->unique()
                    ->values()
                    ->all(),
                'enlaceWhatsapp' => $this->soporte->enlaceWhatsapp(),
                'urlCerrarSesion' => Filament::getCurrentOrDefaultPanel()->getLogoutUrl(),
            ], 403)
            ->header('Cache-Control', 'no-store')
            ->header('X-Robots-Tag', 'noindex');
    }
}
