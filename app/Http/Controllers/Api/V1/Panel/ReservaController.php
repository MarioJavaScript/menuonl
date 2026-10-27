<?php

namespace App\Http\Controllers\Api\V1\Panel;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\Panel\ReservaResource;
use App\Models\Reserva;
use App\Models\Restaurante;
use App\Services\GestionDeReservas;
use App\Services\ReservasDelDia;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class ReservaController extends Controller
{
    public function __construct(private ReservasDelDia $dias, private GestionDeReservas $gestion) {}

    /**
     * Reservations of one day (today in the restaurant's time zone by default), ordered by time.
     * With proximas=1 it returns the ones from today onward (up to ReservasDelDia::MAXIMO_PROXIMAS), ordered by date and time.
     * The response carries "hoy": today's date in the restaurant's time zone.
     */
    public function index(Request $request, Restaurante $restaurante): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', Reserva::class);

        $datos = $request->validate([
            'fecha' => ['nullable', 'date_format:Y-m-d'],
            'proximas' => ['nullable', 'boolean'],
        ]);
        $hoy = $this->dias->hoy($restaurante);

        $reservas = $request->boolean('proximas')
            ? $this->dias->proximas($restaurante, $hoy)
            : $this->dias->delDia($restaurante, $datos['fecha'] ?? $hoy);

        return ReservaResource::collection($reservas)->additional(['hoy' => $hoy]);
    }

    public function store(Request $request, Restaurante $restaurante): JsonResponse
    {
        $reserva = $this->gestion->crear($request->user(), $restaurante, $request->all());

        return (new ReservaResource($reserva))->response()->setStatusCode(201);
    }

    public function update(Request $request, Restaurante $restaurante, Reserva $reserva): ReservaResource
    {
        return new ReservaResource($this->gestion->actualizar($request->user(), $restaurante, $reserva, $request->all()));
    }

    public function destroy(Request $request, Restaurante $restaurante, Reserva $reserva): JsonResponse
    {
        $this->gestion->eliminar($request->user(), $restaurante, $reserva);

        return response()->json(null, 204);
    }
}
