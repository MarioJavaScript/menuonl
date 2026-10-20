<?php

namespace App\Http\Middleware;

use App\Models\Restaurante;
use App\Models\Scopes\RestauranteScope;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Binds a panel API request to one restaurant of the signed-in user.
 *
 * Runs before route model binding, so every tenant-scoped model resolved afterwards is already
 * limited to that restaurant. Non-members and inactive accounts get the same 404 as an unknown slug.
 */
class ContextoRestaurantePanel
{
    public function handle(Request $request, Closure $next): Response
    {
        $usuario = $request->user();
        $slug = $request->route('restaurante');
        $restaurante = is_string($slug) ? Restaurante::where('slug', $slug)->first() : null;

        if (
            ! $usuario instanceof User
            || ! $usuario->activo
            || $restaurante === null
            || ! $restaurante->isActivo()
            || ! $usuario->canAccessTenant($restaurante)
        ) {
            abort(404, 'Restaurante no encontrado.');
        }

        RestauranteScope::setRestauranteId($restaurante->getKey());
        $request->attributes->set('restaurante_panel', $restaurante);

        try {
            return $next($request);
        } finally {
            RestauranteScope::setRestauranteId(null);
        }
    }
}
