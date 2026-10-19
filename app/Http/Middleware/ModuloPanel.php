<?php

namespace App\Http\Middleware;

use App\Models\Scopes\RestauranteScope;
use App\Services\PermisosPanel;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Rejects (403) panel API calls to a module the user's role in the active restaurant does not include.
 *
 * Must run after ContextoRestaurantePanel, which sets the active restaurant.
 */
class ModuloPanel
{
    public function handle(Request $request, Closure $next, string $modulo): Response
    {
        $restauranteId = RestauranteScope::getRestauranteId();

        abort_if(
            $restauranteId === null || ! PermisosPanel::permite($request->user(), $restauranteId, $modulo),
            403,
            'Tu rol no tiene acceso a este módulo.'
        );

        return $next($request);
    }
}
