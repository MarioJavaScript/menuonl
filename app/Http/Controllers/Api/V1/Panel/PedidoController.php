<?php

namespace App\Http\Controllers\Api\V1\Panel;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\Panel\PedidoResource;
use App\Models\Pedido;
use App\Models\Restaurante;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class PedidoController extends Controller
{
    /**
     * Active orders plus those delivered since the start of today (restaurant time zone).
     */
    public function index(Restaurante $restaurante): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', Pedido::class);

        $inicioDelDia = now($restaurante->zona_horaria)->startOfDay()->setTimezone(config('app.timezone'));

        return PedidoResource::collection(
            Pedido::where(fn ($q) => $q
                ->where('estado', '!=', 'entregado')
                ->orWhere('entregado_at', '>=', $inicioDelDia))
                ->oldest()
                ->get()
        );
    }

    public function store(Request $request, Restaurante $restaurante): JsonResponse
    {
        Gate::authorize('create', Pedido::class);

        $datos = $request->validate([
            'mesa' => ['required', 'string', 'max:20'],
            'detalle' => ['required', 'string', 'max:255'],
            'total' => ['required', 'numeric', 'between:0,100000'],
            'estado' => ['nullable', Rule::in(['nuevo', 'cocina'])],
        ]);

        $pedido = Pedido::create([
            ...$datos,
            'mesa' => trim($datos['mesa']),
            'detalle' => trim($datos['detalle']),
            'estado' => $datos['estado'] ?? 'nuevo',
        ]);

        return (new PedidoResource($pedido))->response()->setStatusCode(201);
    }

    /**
     * Move the order one step forward in the kitchen flow.
     */
    public function avanzar(Restaurante $restaurante, Pedido $pedido): PedidoResource|JsonResponse
    {
        Gate::authorize('update', $pedido);

        $siguiente = $pedido->siguienteEstado();

        if ($siguiente === null) {
            return response()->json(['message' => 'El pedido ya fue entregado.'], 422);
        }

        $pedido->update([
            'estado' => $siguiente,
            'entregado_at' => $siguiente === 'entregado' ? now() : null,
        ]);

        return new PedidoResource($pedido->refresh());
    }
}
