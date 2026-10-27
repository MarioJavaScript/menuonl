<?php

namespace App\Http\Controllers\Api\V1\Panel;

use App\Http\Controllers\Controller;
use App\Models\Reserva;
use App\Models\Restaurante;
use App\Services\PermisosPanel;
use App\Services\ReservasDelDia;
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

        return response()->json(['data' => $restaurantes->where('estado', 'activo')->orderBy('nombre')->get(['restaurantes.id', 'nombre', 'slug', 'plan'])
            ->map(fn (Restaurante $r): array => [
                'slug' => $r->slug,
                'nombre' => $r->nombre,
                'modulos' => PermisosPanel::modulosDe($usuario, $r),
            ])->filter(fn (array $r): bool => $r['modulos'] !== [])->values()]);
    }

    /**
     * Figures for the day shown on the panel home. Each role only receives the figures of its own modules.
     */
    public function __invoke(Request $request, Restaurante $restaurante, ReservasDelDia $reservas): JsonResponse
    {
        $modulos = PermisosPanel::modulosDe($request->user(), $restaurante->id);
        $datos = ['restaurante' => $restaurante->nombre];

        if (in_array('reservas', $modulos, true)) {
            Gate::authorize('viewAny', Reserva::class);
            $resumen = $reservas->resumenDelDia($restaurante);
            $datos['comensales_esperados'] = $resumen['comensales_esperados'];
            $datos['reservas_por_confirmar'] = $resumen['por_confirmar'];
        }

        return response()->json(['data' => $datos]);
    }
}
