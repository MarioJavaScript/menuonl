<?php

namespace App\Http\Controllers\Api\V1\Panel;

use App\Http\Controllers\Controller;
use App\Models\Contacto;
use App\Models\Horario;
use App\Models\Restaurante;
use App\Services\Catalogos;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Restaurant settings from the panel (module "ajustes"): contact data, map, hours, reservation capacity
 * and public site template. Reuses the existing `contactos` and `horarios` tables.
 */
class AjustesController extends Controller
{
    /**
     * Contact types the panel edits as single fields.
     *
     * @var list<string>
     */
    private const array CONTACTOS = ['telefono', 'direccion', 'instagram', 'facebook'];

    public function show(Restaurante $restaurante): JsonResponse
    {
        return response()->json(['data' => $this->datos($restaurante)]);
    }

    public function update(Request $request, Restaurante $restaurante): JsonResponse
    {
        $datos = $request->validate([
            'nombre' => ['sometimes', 'required', 'string', 'max:150'],
            'eslogan' => ['sometimes', 'nullable', 'string', 'max:120'],
            'descripcion' => ['sometimes', 'nullable', 'string', 'max:500'],
            'whatsapp' => ['sometimes', 'nullable', 'string', 'max:20', 'regex:/\A\+?[0-9 ()-]{8,20}\z/'],
            'telefono' => ['sometimes', 'nullable', 'string', 'max:255'],
            'direccion' => ['sometimes', 'nullable', 'string', 'max:255'],
            'instagram' => ['sometimes', 'nullable', 'string', 'max:255'],
            'facebook' => ['sometimes', 'nullable', 'string', 'max:255'],
            'latitud' => ['nullable', 'numeric', 'between:-90,90', 'required_with:longitud'],
            'longitud' => ['nullable', 'numeric', 'between:-180,180', 'required_with:latitud'],
            'plantilla' => ['sometimes', 'required', Rule::in(array_keys(Catalogos::plantillasSitio()))],
            'reservas_activas' => ['sometimes', 'required', 'boolean'],
            'reservas_cupo' => ['sometimes', 'required', 'integer', 'between:1,500'],
            'reservas_franja_min' => ['sometimes', 'required', Rule::in([30, 60, 90, 120])],
        ]);

        DB::transaction(function () use ($datos, $restaurante): void {
            $columnas = collect($datos)->only(['nombre', 'eslogan', 'descripcion', 'latitud', 'longitud', 'reservas_activas', 'reservas_cupo', 'reservas_franja_min'])->all();

            if (array_key_exists('whatsapp', $datos)) {
                $columnas['whatsapp_pedidos'] = $this->limpio($datos['whatsapp']);
            }

            if (array_key_exists('plantilla', $datos)) {
                $columnas['sitio_plantilla'] = $datos['plantilla'];
            }

            if (isset($columnas['nombre'])) {
                $columnas['nombre'] = trim($columnas['nombre']);
            }

            $restaurante->update($columnas);

            foreach (self::CONTACTOS as $tipo) {
                if (array_key_exists($tipo, $datos)) {
                    $this->guardarContacto($tipo, $this->limpio($datos[$tipo]));
                }
            }
        });

        return response()->json(['data' => $this->datos($restaurante->refresh())]);
    }

    /**
     * Set the single shift of one weekday (0 = domingo ... 6 = sábado).
     *
     * Days with several shifts are edited in the advanced admin so no shift is lost silently.
     */
    public function horario(Request $request, Restaurante $restaurante, int $dia): JsonResponse
    {
        abort_unless($dia >= 0 && $dia <= 6, 404);

        $datos = $request->validate([
            'cerrado' => ['required', 'boolean'],
            'abre' => ['exclude_if:cerrado,true', 'required', 'date_format:H:i'],
            'cierra' => ['exclude_if:cerrado,true', 'required', 'date_format:H:i'],
        ]);

        $turnos = Horario::where('dia_semana', $dia)->get();

        if ($turnos->count() > 1) {
            return response()->json(['message' => 'Este día tiene varios turnos; edítalo en la administración avanzada.'], 422);
        }

        $valores = $datos['cerrado']
            ? ['cerrado' => true, 'abre' => null, 'cierra' => null]
            : ['cerrado' => false, 'abre' => $datos['abre'].':00', 'cierra' => $datos['cierra'].':00'];

        $turno = $turnos->first();
        $turno === null ? Horario::create(['dia_semana' => $dia, ...$valores]) : $turno->update($valores);

        return response()->json(['data' => $this->datos($restaurante)['horarios']]);
    }

    /**
     * @return array<string, mixed>
     */
    private function datos(Restaurante $restaurante): array
    {
        $contactos = Contacto::orderBy('orden')->orderBy('id')->get()->groupBy('tipo');
        $valor = fn (string $tipo): ?string => $contactos->get($tipo)?->first()?->valor;
        $horarios = Horario::orderBy('abre')->get()->groupBy('dia_semana');

        return [
            'nombre' => $restaurante->nombre,
            'eslogan' => $restaurante->eslogan,
            'descripcion' => $restaurante->descripcion,
            'whatsapp' => $restaurante->whatsapp_pedidos,
            'telefono' => $valor('telefono'),
            'direccion' => $valor('direccion'),
            'instagram' => $valor('instagram'),
            'facebook' => $valor('facebook'),
            'latitud' => $restaurante->latitud,
            'longitud' => $restaurante->longitud,
            'plantilla' => $restaurante->sitio_plantilla,
            'plantillas' => collect(Catalogos::plantillasSitio())->map(fn (array $p, string $clave): array => ['clave' => $clave, ...$p])->values(),
            'reservas_activas' => $restaurante->reservas_activas,
            'reservas_cupo' => $restaurante->reservas_cupo,
            'reservas_franja_min' => $restaurante->reservas_franja_min,
            'horarios' => collect(range(0, 6))->map(function (int $dia) use ($horarios): array {
                $turnos = $horarios->get($dia, collect());
                $primero = $turnos->first();

                return [
                    'dia' => $dia,
                    'nombre' => Catalogos::diasSemana()[$dia],
                    'cerrado' => $turnos->isEmpty() || $turnos->contains('cerrado', true),
                    'abre' => $primero?->abre ? substr((string) $primero->abre, 0, 5) : null,
                    'cierra' => $primero?->cierra ? substr((string) $primero->cierra, 0, 5) : null,
                    'varios' => $turnos->count() > 1,
                ];
            })->values(),
        ];
    }

    private function guardarContacto(string $tipo, ?string $valor): void
    {
        $contacto = Contacto::where('tipo', $tipo)->orderBy('orden')->orderBy('id')->first();

        if ($valor === null) {
            $contacto?->delete();

            return;
        }

        $contacto === null
            ? Contacto::create(['tipo' => $tipo, 'valor' => $valor, 'orden' => 0, 'visible' => true])
            : $contacto->update(['valor' => $valor]);
    }

    private function limpio(?string $valor): ?string
    {
        $valor = trim((string) $valor);

        return $valor === '' ? null : $valor;
    }
}
