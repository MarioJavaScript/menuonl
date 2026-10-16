<?php

namespace App\Http\Middleware;

use App\Models\Restaurante;
use App\Models\Scopes\RestauranteScope;
use Closure;
use Filament\Facades\Filament;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetCurrentRestauranteContext
{
    /**
     * Handle an incoming request and bind the active tenant to RestauranteScope.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $tenant = Filament::getTenant();

        if (! $tenant) {
            $routeTenant = $request->route('tenant');
            if ($routeTenant instanceof Restaurante) {
                $tenant = $routeTenant;
            } elseif (is_string($routeTenant) || is_numeric($routeTenant)) {
                $tenant = Restaurante::where('slug', $routeTenant)
                    ->orWhere('id', $routeTenant)
                    ->first();
            }
        }

        if ($tenant instanceof Restaurante) {
            RestauranteScope::setRestauranteId($tenant->id);
        } else {
            RestauranteScope::setRestauranteId(null);
        }

        return $next($request);
    }
}
