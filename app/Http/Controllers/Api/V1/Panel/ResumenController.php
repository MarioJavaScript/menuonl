<?php

namespace App\Http\Controllers\Api\V1\Panel;

use App\Http\Controllers\Controller;
use App\Models\Pedido;
use App\Models\Reserva;
use App\Models\Restaurante;
use App\Services\PermisosPanel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class ResumenController extends Controller
{
    /**
     * Restaurants the signed-in user can manage from the panel, with their role and modules in each.
     */
    public function restaurantes(Request $request): JsonResponse
    {
        $usuario = $request->user();
        $restaurantes = $usuario->isSuperadmin() ? Restaurante::query()->limit(50) : $usuario->restaurantes()->wherePivot('activo', true);

        return response()->json(['data' => $restaurantes->where('estado', 'activo')->orderBy('nombre')->get(['restaurantes.id', 'nombre', 'slug'])
            ->map(fn (Restaurante $r): array => [
                'slug' => $r->slug,
                'nombre' => $r->nombre,
                'modulos' => PermisosPanel::modulosDe($usuario, $r->id),
            ])->filter(fn (array $r): bool => $r['modulos'] !== [])->values()]);
    }

    /**
     * Figures for the day shown on the panel home. Each role only receives the figures of its own modules.
     */
    public function __invoke(Request $request, Restaurante $restaurante): JsonResponse
    {
        $modulos = PermisosPanel::modulosDe($request->user(), $restaurante->id);
        $hoy = now($restaurante->zona_horaria);
        $inicioDelDia = $hoy->copy()->startOfDay()->setTimezone(config('app.timezone'));
        $datos = ['restaurante' => $restaurante->nombre];

        if (in_array('reservas', $modulos, true)) {
            Gate::authorize('viewAny', Reserva::class);
            $reservasDeHoy = Reserva::whereDate('fecha', $hoy->toDateString())->where('estado', '!=', 'cancelada');
            $datos['comensales_esperados'] = (int) (clone $reservasDeHoy)->sum('personas');
            $datos['reservas_por_confirmar'] = (clone $reservasDeHoy)->where('estado', 'pendiente')->count();
        }

        if (in_array('pedidos', $modulos, true)) {
            Gate::authorize('viewAny', Pedido::class);
            $datos['pedidos_activos'] = Pedido::where('estado', '!=', 'entregado')->count();
        }

        if (in_array('ajustes', $modulos, true)) {
            $datos['ventas_hoy'] = (float) Pedido::where('estado', 'entregado')->where('entregado_at', '>=', $inicioDelDia)->sum('total');
        }

        return response()->json(['data' => $datos]);
    }
}
