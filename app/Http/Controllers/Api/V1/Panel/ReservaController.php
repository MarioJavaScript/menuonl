<?php

namespace App\Http\Controllers\Api\V1\Panel;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\Panel\ReservaResource;
use App\Models\Reserva;
use App\Models\Restaurante;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class ReservaController extends Controller
{
    /**
     * Reservations of one day (today in the restaurant's time zone by default), ordered by time.
     */
    public function index(Request $request, Restaurante $restaurante): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', Reserva::class);

        $datos = $request->validate(['fecha' => ['nullable', 'date_format:Y-m-d']]);
        $fecha = $datos['fecha'] ?? now($restaurante->zona_horaria)->toDateString();

        return ReservaResource::collection(
            Reserva::whereDate('fecha', $fecha)->orderBy('hora')->orderBy('id')->get()
        );
    }

    public function store(Request $request, Restaurante $restaurante): JsonResponse
    {
        Gate::authorize('create', Reserva::class);

        $datos = $request->validate([
            'nombre' => ['required', 'string', 'max:100'],
            'personas' => ['required', 'integer', 'between:1,30'],
            'hora' => ['required', 'date_format:H:i'],
            'fecha' => ['nullable', 'date_format:Y-m-d'],
            'estado' => ['nullable', Rule::in(Reserva::ESTADOS)],
            'nota' => ['nullable', 'string', 'max:255'],
        ]);

        $reserva = Reserva::create([
            ...$datos,
            'nombre' => trim($datos['nombre']),
            'fecha' => $datos['fecha'] ?? now($restaurante->zona_horaria)->toDateString(),
            'estado' => $datos['estado'] ?? 'confirmada',
        ]);

        return (new ReservaResource($reserva))->response()->setStatusCode(201);
    }

    public function update(Request $request, Restaurante $restaurante, Reserva $reserva): ReservaResource
    {
        Gate::authorize('update', $reserva);

        $datos = $request->validate([
            'nombre' => ['sometimes', 'required', 'string', 'max:100'],
            'personas' => ['sometimes', 'required', 'integer', 'between:1,30'],
            'hora' => ['sometimes', 'required', 'date_format:H:i'],
            'fecha' => ['sometimes', 'required', 'date_format:Y-m-d'],
            'estado' => ['sometimes', 'required', Rule::in(Reserva::ESTADOS)],
            'nota' => ['sometimes', 'nullable', 'string', 'max:255'],
        ]);

        $reserva->update($datos);

        return new ReservaResource($reserva->refresh());
    }

    public function destroy(Restaurante $restaurante, Reserva $reserva): JsonResponse
    {
        Gate::authorize('delete', $reserva);

        $reserva->delete();

        return response()->json(null, 204);
    }
}
