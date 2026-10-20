<?php

namespace App\Services;

use App\Models\Horario;
use App\Models\Reserva;
use App\Models\Restaurante;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Online reservations: time slots from the opening hours, capacity per slot and creation of the pending reservation.
 *
 * Must run with the RestauranteScope context already set to the restaurant.
 *
 * A slot starts at each opening time and repeats every `reservas_franja_min` minutes while it ends before closing.
 * Its capacity (`reservas_cupo`, in guests) counts the not-cancelled reservations of that date whose time falls inside it.
 */
class ReservasPublicas
{
    /**
     * Slots of one date, flagged with whether `personas` more guests still fit.
     *
     * @return list<array{hora: string, disponible: bool}>
     */
    public function franjas(Restaurante $restaurante, CarbonImmutable $fecha, int $personas = 1): array
    {
        $dia = $fecha->dayOfWeek;
        $turnos = Horario::where('dia_semana', $dia)->orderBy('abre')->get();

        if ($turnos->contains('cerrado', true)) {
            return [];
        }

        $ocupacion = Reserva::whereDate('fecha', $fecha->toDateString())
            ->where('estado', '!=', 'cancelada')
            ->get(['hora', 'personas']);

        $limite = now($restaurante->zona_horaria)->addMinutes((int) config('menuonl.reservas.anticipacion_min'));
        $paso = max(15, (int) $restaurante->reservas_franja_min);
        $franjas = [];

        foreach ($turnos as $turno) {
            if ($turno->abre === null || $turno->cierra === null) {
                continue;
            }

            $inicio = $fecha->setTimeFromTimeString((string) $turno->abre);
            $fin = $fecha->setTimeFromTimeString((string) $turno->cierra);
            $fin = $fin <= $inicio ? $fin->addDay() : $fin;

            for ($t = $inicio; $t->addMinutes($paso) <= $fin; $t = $t->addMinutes($paso)) {
                $hasta = $t->addMinutes($paso);
                $desde = $t->format('H:i:s');
                $ocupadas = $ocupacion
                    ->filter(fn (Reserva $r): bool => (string) $r->hora >= $desde && ($hasta->isSameDay($t) ? (string) $r->hora < $hasta->format('H:i:s') : true))
                    ->sum('personas');

                $franjas[$t->format('H:i')] = [
                    'hora' => $t->format('H:i'),
                    'disponible' => $t >= $limite && $ocupadas + $personas <= $restaurante->reservas_cupo,
                ];
            }
        }

        ksort($franjas);

        return array_values($franjas);
    }

    /**
     * Create a pending reservation, re-checking date, slot and capacity inside a transaction.
     *
     * @param  array{nombre: string, telefono: string, personas: int, fecha: string, hora: string, nota?: ?string}  $datos
     *
     * @throws ValidationException
     */
    public function crear(Restaurante $restaurante, array $datos): Reserva
    {
        if (! $restaurante->reservas_activas) {
            throw ValidationException::withMessages(['fecha' => 'Por ahora no recibimos reservas en línea.']);
        }

        $fecha = CarbonImmutable::createFromFormat('Y-m-d', $datos['fecha'], $restaurante->zona_horaria)->startOfDay();
        $hoy = now($restaurante->zona_horaria)->startOfDay();

        if ($fecha < $hoy) {
            throw ValidationException::withMessages(['fecha' => 'Esa fecha ya pasó.']);
        }

        if ($fecha > $hoy->addDays((int) config('menuonl.reservas.max_dias'))) {
            throw ValidationException::withMessages(['fecha' => 'Aún no abrimos reservas para esa fecha.']);
        }

        return DB::transaction(function () use ($restaurante, $datos, $fecha): Reserva {
            // Serializes concurrent bookings of the same restaurant so the last seats cannot be sold twice.
            Restaurante::whereKey($restaurante->getKey())->lockForUpdate()->first();

            $franja = collect($this->franjas($restaurante, $fecha, (int) $datos['personas']))->firstWhere('hora', $datos['hora']);

            if ($franja === null) {
                throw ValidationException::withMessages(['hora' => 'Ese día o esa hora no tenemos servicio.']);
            }

            if (! $franja['disponible']) {
                throw ValidationException::withMessages(['hora' => 'Esa franja está llena o ya no admite reservas. Elige otra hora.']);
            }

            return Reserva::create([
                'nombre' => $datos['nombre'],
                'telefono' => $datos['telefono'],
                'personas' => $datos['personas'],
                'fecha' => $fecha->toDateString(),
                'hora' => $datos['hora'].':00',
                'estado' => 'pendiente',
                'origen' => 'sitio',
                'nota' => $datos['nota'] ?? null,
            ]);
        });
    }
}
