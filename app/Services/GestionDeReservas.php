<?php

namespace App\Services;

use App\Models\Reserva;
use App\Models\Restaurante;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

/**
 * Reservation writes of the service panel, shared by the panel API and the Filament pages.
 *
 * Every operation runs the same steps, in this order: module of the role and plan (PermisosPanel), ownership of the
 * record by the restaurant, policy, validation of the business rules, and the write. So a record of another restaurant
 * is a 404 (as the route binding already does on the API) and a role without permission gets 403 before a validation error.
 *
 * The panel does not look at the capacity of the slot (reception may overbook) nor at past dates; only the public site
 * does (ReservasPublicas). Any state can move to any other: "restaurar" is just `pendiente`.
 *
 * Concurrency: Eloquent writes only the changed columns, so two people changing different fields do not overwrite each
 * other. When two people may change the same field, pass what the screen showed in `$esperado`: the write is then a
 * single UPDATE guarded by it, and a mismatch throws ConflictoDeReserva instead of silently overwriting.
 */
class GestionDeReservas
{
    /**
     * Fields a caller may require to be unchanged when updating.
     */
    private const array CAMPOS_ESPERABLES = ['estado', 'hora', 'fecha'];

    public function __construct(private ReservasDelDia $dias) {}

    /**
     * Validation rules, in one place. `$parcial` is the update: only the fields that come are checked.
     *
     * @return array<string, list<mixed>>
     */
    public static function reglas(bool $parcial = false): array
    {
        $antes = $parcial ? ['sometimes', 'required'] : ['required'];
        $opcional = $parcial ? ['sometimes', 'nullable'] : ['nullable'];

        return [
            'nombre' => [...$antes, 'string', 'max:100'],
            'personas' => [...$antes, 'integer', 'between:1,30'],
            'hora' => [...$antes, 'date_format:H:i'],
            'fecha' => [...($parcial ? $antes : $opcional), 'date_format:Y-m-d'],
            'estado' => [...($parcial ? $antes : $opcional), Rule::in(Reserva::ESTADOS)],
            'nota' => [...$opcional, 'string', 'max:255'],
        ];
    }

    /**
     * Create a reservation: confirmed by default, today by default (restaurant time zone), origin "panel".
     *
     * @param  array<string, mixed>  $datos
     */
    public function crear(User $actor, Restaurante $restaurante, array $datos): Reserva
    {
        $this->autorizarModulo($actor, $restaurante);
        Gate::forUser($actor)->authorize('create', Reserva::class);

        $datos = Validator::make($datos, self::reglas())->validate();

        return Reserva::create([
            ...$datos,
            'restaurante_id' => $restaurante->getKey(),
            'nombre' => trim($datos['nombre']),
            'fecha' => $datos['fecha'] ?? $this->dias->hoy($restaurante),
            'estado' => $datos['estado'] ?? 'confirmada',
        ]);
    }

    /**
     * Update any of name, guests, time, date, state and note.
     *
     * @param  array<string, mixed>  $datos
     * @param  array<string, string>  $esperado  current values the record must still have (estado, hora, fecha)
     *
     * @throws ConflictoDeReserva when `$esperado` no longer matches
     */
    public function actualizar(User $actor, Restaurante $restaurante, Reserva $reserva, array $datos, array $esperado = []): Reserva
    {
        $this->autorizarModulo($actor, $restaurante);
        $this->exigirDelRestaurante($restaurante, $reserva);
        Gate::forUser($actor)->authorize('update', $reserva);

        $datos = Validator::make($datos, self::reglas(parcial: true))->validate();

        if ($esperado === []) {
            $reserva->update($datos);

            return $reserva->refresh();
        }

        $esperado = array_intersect_key($esperado, array_flip(self::CAMPOS_ESPERABLES));
        $filas = Reserva::whereKey($reserva->getKey())
            ->where($esperado)
            ->update([...$datos, 'updated_at' => now()]);

        if ($filas !== 1) {
            throw new ConflictoDeReserva;
        }

        return $reserva->refresh();
    }

    /**
     * Set the state. With `$estadoEsperado`, only if the reservation is still in that state.
     */
    public function cambiarEstado(User $actor, Restaurante $restaurante, Reserva $reserva, string $estado, ?string $estadoEsperado = null): Reserva
    {
        return $this->actualizar($actor, $restaurante, $reserva, ['estado' => $estado], $estadoEsperado === null ? [] : ['estado' => $estadoEsperado]);
    }

    public function confirmar(User $actor, Restaurante $restaurante, Reserva $reserva, ?string $estadoEsperado = null): Reserva
    {
        return $this->cambiarEstado($actor, $restaurante, $reserva, 'confirmada', $estadoEsperado);
    }

    public function marcarLlegada(User $actor, Restaurante $restaurante, Reserva $reserva, ?string $estadoEsperado = null): Reserva
    {
        return $this->cambiarEstado($actor, $restaurante, $reserva, 'llego', $estadoEsperado);
    }

    public function cancelar(User $actor, Restaurante $restaurante, Reserva $reserva, ?string $estadoEsperado = null): Reserva
    {
        return $this->cambiarEstado($actor, $restaurante, $reserva, 'cancelada', $estadoEsperado);
    }

    public function restaurar(User $actor, Restaurante $restaurante, Reserva $reserva, ?string $estadoEsperado = null): Reserva
    {
        return $this->cambiarEstado($actor, $restaurante, $reserva, 'pendiente', $estadoEsperado);
    }

    /**
     * Move the reservation to another time and/or date. `$esperado` as in `actualizar()`.
     *
     * @param  array<string, string>  $esperado
     */
    public function mover(User $actor, Restaurante $restaurante, Reserva $reserva, ?string $hora, ?string $fecha = null, array $esperado = []): Reserva
    {
        return $this->actualizar($actor, $restaurante, $reserva, array_filter(['hora' => $hora, 'fecha' => $fecha], fn (?string $valor): bool => $valor !== null), $esperado);
    }

    public function eliminar(User $actor, Restaurante $restaurante, Reserva $reserva): void
    {
        $this->autorizarModulo($actor, $restaurante);
        $this->exigirDelRestaurante($restaurante, $reserva);
        Gate::forUser($actor)->authorize('delete', $reserva);

        $reserva->delete();
    }

    /**
     * The module "reservas" must be open for the role and the plan (what the ModuloPanel middleware checks on the API).
     */
    private function autorizarModulo(User $actor, Restaurante $restaurante): void
    {
        if (! PermisosPanel::permite($actor, $restaurante, 'reservas')) {
            throw new AuthorizationException('Tu rol no tiene acceso a este módulo.');
        }
    }

    /**
     * A reservation of another restaurant behaves as if it did not exist.
     */
    private function exigirDelRestaurante(Restaurante $restaurante, Reserva $reserva): void
    {
        if ((int) $reserva->restaurante_id !== (int) $restaurante->getKey()) {
            throw (new ModelNotFoundException)->setModel(Reserva::class, [$reserva->getKey()]);
        }
    }
}
