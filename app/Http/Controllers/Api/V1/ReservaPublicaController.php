<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Restaurante;
use App\Models\Scopes\RestauranteScope;
use App\Services\ReservasPublicas;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Public reservations from the restaurant site. Nothing here trusts the browser: every rule is checked again
 * by ReservasPublicas, and the restaurant always comes from the URL slug.
 */
class ReservaPublicaController extends Controller
{
    public function __construct(private ReservasPublicas $reservas) {}

    public function disponibilidad(Request $request, string $slug): JsonResponse
    {
        return $this->enRestaurante($slug, function (Restaurante $restaurante) use ($request): JsonResponse {
            $datos = $request->validate([
                'fecha' => ['required', 'date_format:Y-m-d'],
                'personas' => ['nullable', 'integer', 'between:1,'.config('menuonl.reservas.max_personas')],
            ]);

            $fecha = CarbonImmutable::createFromFormat('Y-m-d', $datos['fecha'], $restaurante->zona_horaria)->startOfDay();
            $hoy = now($restaurante->zona_horaria)->startOfDay();
            $franjas = $restaurante->reservas_activas && $fecha >= $hoy && $fecha <= $hoy->addDays((int) config('menuonl.reservas.max_dias'))
                ? $this->reservas->franjas($restaurante, $fecha, (int) ($datos['personas'] ?? 1))
                : [];

            return response()->json(['data' => ['fecha' => $datos['fecha'], 'franjas' => $franjas]]);
        });
    }

    public function store(Request $request, string $slug): JsonResponse
    {
        return $this->enRestaurante($slug, function (Restaurante $restaurante) use ($request): JsonResponse {
            // Honeypot: real visitors never see this field. Bots get a convincing answer and nothing is stored.
            if ($request->filled('sitio_web')) {
                return response()->json(['data' => ['mensaje' => 'Recibimos tu solicitud.']], 201);
            }

            $datos = $request->validate([
                'nombre' => ['required', 'string', 'min:2', 'max:100', 'regex:/\A[\p{L}\p{M}\' .-]+\z/u'],
                'telefono' => ['required', 'string', 'regex:/\A\+?[0-9 ()-]{8,20}\z/'],
                'personas' => ['required', 'integer', 'between:1,'.config('menuonl.reservas.max_personas')],
                'fecha' => ['required', 'date_format:Y-m-d'],
                'hora' => ['required', 'date_format:H:i'],
                'nota' => ['nullable', 'string', 'max:255'],
                'ts' => ['required', 'integer'],
            ], [
                'nombre.regex' => 'El nombre solo puede llevar letras, espacios, puntos y guiones.',
                'telefono.regex' => 'Escribe un teléfono válido, con lada.',
            ]);

            $segundos = now()->timestamp - (int) $datos['ts'];
            if ($segundos < (int) config('menuonl.reservas.espera_min_segundos') || $segundos > 43200) {
                return response()->json(['message' => 'No pudimos validar tu solicitud. Recarga la página e inténtalo de nuevo.'], 422);
            }

            $reserva = $this->reservas->crear($restaurante, [
                ...$datos,
                'nombre' => trim($datos['nombre']),
                'nota' => isset($datos['nota']) ? trim(strip_tags($datos['nota'])) : null,
            ]);

            $mensaje = "Hola, hice una reserva en {$restaurante->nombre} a nombre de {$reserva->nombre} para {$reserva->personas} personas el {$reserva->fecha->format('d/m/Y')} a las ".substr((string) $reserva->hora, 0, 5).'.';
            $digitos = preg_replace('/\D/', '', (string) $restaurante->whatsapp_pedidos);

            return response()->json(['data' => [
                'mensaje' => 'Recibimos tu solicitud. Te confirmaremos por teléfono o WhatsApp.',
                'nombre' => $reserva->nombre,
                'fecha' => $reserva->fecha->toDateString(),
                'hora' => substr((string) $reserva->hora, 0, 5),
                'personas' => $reserva->personas,
                'whatsapp_url' => $digitos ? 'https://wa.me/'.$digitos.'?text='.rawurlencode($mensaje) : null,
            ]], 201);
        });
    }

    /**
     * Runs the callback with the restaurant of the slug as the active tenant; unknown, suspended or deleted ones answer 404.
     *
     * @param  callable(Restaurante): JsonResponse  $callback
     */
    private function enRestaurante(string $slug, callable $callback): JsonResponse
    {
        $restaurante = Restaurante::where('slug', $slug)->first();

        if ($restaurante === null || ! $restaurante->isActivo() || $restaurante->trashed()) {
            return response()->json(['message' => 'Restaurante no encontrado o no disponible.'], 404);
        }

        try {
            RestauranteScope::setRestauranteId($restaurante->id);

            return $callback($restaurante);
        } finally {
            RestauranteScope::setRestauranteId(null);
        }
    }
}
