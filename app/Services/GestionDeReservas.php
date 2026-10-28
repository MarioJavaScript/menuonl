<?php

namespace App\Services;

use App\Models\Reserva;
use App\Models\ReservaEvento;
use App\Models\Restaurante;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use InvalidArgumentException;

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
 *
 * History: every write that changes something also stores a ReservaEvento (who, what, when, from where) in the same
 * transaction. `$origen` says where the call came from: "api" (the default; the old panel API is gone, the value stays for
 * existing history rows), "panel" or "admin" (the Filament page). Events keep state, time and date changes only; never name, phone, note or party size.
 */
class GestionDeReservas
{
    /**
     * Fields a caller may require to be unchanged when updating.
     */
    private const array CAMPOS_ESPERABLES = ['estado', 'hora', 'fecha'];

    /**
     * History action for each state a reservation can be set to.
     */
    private const array ACCION_POR_ESTADO = [
        'pendiente' => 'restaurada',
        'confirmada' => 'confirmada',
        'llego' => 'llego',
        'cancelada' => 'cancelada',
    ];

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
     * Create a reservation: confirmed by default, today by default (restaurant time zone), origin given by the caller.
     *
     * @param  array<string, mixed>  $datos
     */
    public function crear(User $actor, Restaurante $restaurante, array $datos, string $origen = 'api'): Reserva
    {
        $this->exigirOrigenConocido($origen);
        $this->autorizarModulo($actor, $restaurante);
        Gate::forUser($actor)->authorize('create', Reserva::class);

        $datos = Validator::make($datos, self::reglas())->validate();

        return DB::transaction(function () use ($actor, $restaurante, $datos, $origen): Reserva {
            $reserva = Reserva::create([
                ...$datos,
                'restaurante_id' => $restaurante->getKey(),
                'nombre' => trim($datos['nombre']),
                'fecha' => $datos['fecha'] ?? $this->dias->hoy($restaurante),
                'estado' => $datos['estado'] ?? 'confirmada',
            ]);

            $this->registrarEvento($actor, $restaurante, $reserva, 'creada', $origen, null, $this->valores($reserva));

            return $reserva;
        });
    }

    /**
     * Update any of name, guests, time, date, state and note.
     *
     * @param  array<string, mixed>  $datos
     * @param  array<string, string>  $esperado  current values the record must still have (estado, hora, fecha)
     *
     * @throws ConflictoDeReserva when `$esperado` no longer matches
     */
    public function actualizar(User $actor, Restaurante $restaurante, Reserva $reserva, array $datos, array $esperado = [], string $origen = 'api'): Reserva
    {
        $this->exigirOrigenConocido($origen);
        $this->autorizarModulo($actor, $restaurante);
        $this->exigirDelRestaurante($restaurante, $reserva);
        Gate::forUser($actor)->authorize('update', $reserva);

        $datos = Validator::make($datos, self::reglas(parcial: true))->validate();
        $esperado = array_intersect_key($esperado, array_flip(self::CAMPOS_ESPERABLES));

        return DB::transaction(function () use ($actor, $restaurante, $reserva, $datos, $esperado, $origen): Reserva {
            // What the database holds right now, so the history records the real change even if `$reserva` is stale.
            $antes = Reserva::whereKey($reserva->getKey())->lockForUpdate()->first();

            if ($antes === null) {
                throw $esperado === []
                    ? (new ModelNotFoundException)->setModel(Reserva::class, [$reserva->getKey()])
                    : new ConflictoDeReserva;
            }

            $valoresAntes = $this->valores($antes);

            if ($esperado === []) {
                $reserva->update($datos);
            } else {
                $filas = Reserva::whereKey($reserva->getKey())
                    ->where($esperado)
                    ->update([...$datos, 'updated_at' => now()]);

                if ($filas !== 1) {
                    throw new ConflictoDeReserva;
                }
            }

            $reserva->refresh();
            $this->registrarCambio($actor, $restaurante, $reserva, $origen, $valoresAntes);

            return $reserva;
        });
    }

    /**
     * Set the state. With `$estadoEsperado`, only if the reservation is still in that state.
     */
    public function cambiarEstado(User $actor, Restaurante $restaurante, Reserva $reserva, string $estado, ?string $estadoEsperado = null, string $origen = 'api'): Reserva
    {
        return $this->actualizar($actor, $restaurante, $reserva, ['estado' => $estado], $estadoEsperado === null ? [] : ['estado' => $estadoEsperado], $origen);
    }

    public function confirmar(User $actor, Restaurante $restaurante, Reserva $reserva, ?string $estadoEsperado = null, string $origen = 'api'): Reserva
    {
        return $this->cambiarEstado($actor, $restaurante, $reserva, 'confirmada', $estadoEsperado, $origen);
    }

    public function marcarLlegada(User $actor, Restaurante $restaurante, Reserva $reserva, ?string $estadoEsperado = null, string $origen = 'api'): Reserva
    {
        return $this->cambiarEstado($actor, $restaurante, $reserva, 'llego', $estadoEsperado, $origen);
    }

    public function cancelar(User $actor, Restaurante $restaurante, Reserva $reserva, ?string $estadoEsperado = null, string $origen = 'api'): Reserva
    {
        return $this->cambiarEstado($actor, $restaurante, $reserva, 'cancelada', $estadoEsperado, $origen);
    }

    public function restaurar(User $actor, Restaurante $restaurante, Reserva $reserva, ?string $estadoEsperado = null, string $origen = 'api'): Reserva
    {
        return $this->cambiarEstado($actor, $restaurante, $reserva, 'pendiente', $estadoEsperado, $origen);
    }

    /**
     * Move the reservation to another time and/or date. `$esperado` as in `actualizar()`.
     *
     * @param  array<string, string>  $esperado
     */
    public function mover(User $actor, Restaurante $restaurante, Reserva $reserva, ?string $hora, ?string $fecha = null, array $esperado = [], string $origen = 'api'): Reserva
    {
        return $this->actualizar($actor, $restaurante, $reserva, array_filter(['hora' => $hora, 'fecha' => $fecha], fn (?string $valor): bool => $valor !== null), $esperado, $origen);
    }

    public function eliminar(User $actor, Restaurante $restaurante, Reserva $reserva, string $origen = 'api'): void
    {
        $this->exigirOrigenConocido($origen);
        $this->autorizarModulo($actor, $restaurante);
        $this->exigirDelRestaurante($restaurante, $reserva);
        Gate::forUser($actor)->authorize('delete', $reserva);

        DB::transaction(function () use ($actor, $restaurante, $reserva, $origen): void {
            $valoresAntes = $this->valores($reserva);
            $reserva->delete();

            $this->registrarEvento($actor, $restaurante, $reserva, 'eliminada', $origen, $valoresAntes, null);
        });
    }

    /**
     * The module "reservas" must be open for the role and the plan (the same rule the Reservas page applies).
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

    /**
     * The history only knows the origins it can store.
     */
    private function exigirOrigenConocido(string $origen): void
    {
        if (! in_array($origen, ReservaEvento::ORIGENES, true)) {
            throw new InvalidArgumentException("Origen de reserva desconocido: {$origen}.");
        }
    }

    /**
     * State, time ("H:i") and date ("Y-m-d") of a reservation, read from the stored values.
     *
     * @return array{estado: string, hora: string, fecha: string}
     */
    private function valores(Reserva $reserva): array
    {
        return [
            'estado' => (string) $reserva->getRawOriginal('estado'),
            'hora' => substr((string) $reserva->getRawOriginal('hora'), 0, 5),
            'fecha' => substr((string) $reserva->getRawOriginal('fecha'), 0, 10),
        ];
    }

    /**
     * Store the event of an update: nothing when state, time and date stayed the same; the action follows the new state,
     * or is "movida" when only the time or date changed.
     *
     * @param  array{estado: string, hora: string, fecha: string}  $antes
     */
    private function registrarCambio(User $actor, Restaurante $restaurante, Reserva $reserva, string $origen, array $antes): void
    {
        $despues = $this->valores($reserva);
        $cambioEstado = $antes['estado'] !== $despues['estado'];

        if (! $cambioEstado && $antes['hora'] === $despues['hora'] && $antes['fecha'] === $despues['fecha']) {
            return;
        }

        $accion = $cambioEstado ? self::ACCION_POR_ESTADO[$despues['estado']] : 'movida';

        $this->registrarEvento($actor, $restaurante, $reserva, $accion, $origen, $antes, $despues);
    }

    /**
     * Store one event. Of each pair (before and after) only the fields that changed are kept; on create and delete the
     * whole state is kept. Never guest data.
     *
     * @param  array{estado: string, hora: string, fecha: string}|null  $antes
     * @param  array{estado: string, hora: string, fecha: string}|null  $despues
     */
    private function registrarEvento(User $actor, Restaurante $restaurante, Reserva $reserva, string $accion, string $origen, ?array $antes, ?array $despues): void
    {
        $evento = [
            'restaurante_id' => $restaurante->getKey(),
            'reserva_id' => $reserva->getKey(),
            'usuario_id' => $actor->getKey(),
            'accion' => $accion,
            'origen' => $origen,
        ];

        foreach (['estado', 'hora', 'fecha'] as $campo) {
            if ($antes === null || $despues === null || $antes[$campo] !== $despues[$campo]) {
                $evento["{$campo}_antes"] = $antes[$campo] ?? null;
                $evento["{$campo}_despues"] = $despues[$campo] ?? null;
            }
        }

        ReservaEvento::create($evento);
    }
}
