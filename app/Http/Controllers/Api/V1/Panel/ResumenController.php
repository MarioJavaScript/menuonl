<?php

namespace App\Http\Controllers\Api\V1\Panel;

use App\Http\Controllers\Controller;
use App\Models\Pedido;
use App\Models\Reserva;
use App\Models\Restaurante;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class ResumenController extends Controller
{
    /**
     * Restaurants the signed-in user can manage from the panel.
     */
    public function restaurantes(Request $request): JsonResponse
    {
        $usuario = $request->user();
        $restaurantes = $usuario->isSuperadmin() ? Restaurante::query()->limit(50) : $usuario->restaurantes();

        return response()->json(['data' => $restaurantes->where('estado', 'activo')->orderBy('nombre')->get(['restaurantes.id', 'nombre', 'slug'])
            ->map(fn (Restaurante $r): array => ['slug' => $r->slug, 'nombre' => $r->nombre])->values()]);
    }

    /**
     * Figures for the day shown on the panel home.
     */
    public function __invoke(Restaurante $restaurante): JsonResponse
    {
        Gate::authorize('viewAny', Pedido::class);
        Gate::authorize('viewAny', Reserva::class);

        $hoy = now($restaurante->zona_horaria);
        $inicioDelDia = $hoy->copy()->startOfDay()->setTimezone(config('app.timezone'));
        $reservasDeHoy = Reserva::whereDate('fecha', $hoy->toDateString())->where('estado', '!=', 'cancelada');

        return response()->json(['data' => [
            'restaurante' => $restaurante->nombre,
            'ventas_hoy' => (float) Pedido::where('estado', 'entregado')->where('entregado_at', '>=', $inicioDelDia)->sum('total'),
            'comensales_esperados' => (int) (clone $reservasDeHoy)->sum('personas'),
            'reservas_por_confirmar' => (clone $reservasDeHoy)->where('estado', 'pendiente')->count(),
            'pedidos_activos' => Pedido::where('estado', '!=', 'entregado')->count(),
        ]]);
    }
}
