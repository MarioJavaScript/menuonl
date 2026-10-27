<?php

namespace App\Services;

use App\Models\Reserva;
use App\Models\Restaurante;
use Illuminate\Database\Eloquent\Collection;

/**
 * Reservation queries of the service panel: the day, the upcoming ones and the figures of the day.
 *
 * "Today" is the date in the restaurant's time zone. Dates are compared with whereDate on purpose: Eloquent stores a
 * Carbon assigned to `fecha` as "Y-m-d H:i:s" (and a plain string as "Y-m-d"), so an equality on the raw column would
 * miss rows in SQLite. Every query also filters by restaurant, so it stays safe without an active tenant context.
 */
class ReservasDelDia
{
    public const int MAXIMO_PROXIMAS = 200;

    /**
     * Today's date (Y-m-d) in the restaurant's time zone.
     */
    public function hoy(Restaurante $restaurante): string
    {
        return now($restaurante->zona_horaria)->toDateString();
    }

    /**
     * Reservations of one date, ordered by time.
     *
     * @return Collection<int, Reserva>
     */
    public function delDia(Restaurante $restaurante, string $fecha): Collection
    {
        return Reserva::where('restaurante_id', $restaurante->getKey())
            ->whereDate('fecha', $fecha)
            ->orderBy('hora')->orderBy('id')
            ->get();
    }

    /**
     * Reservations from `$hoy` onward (up to MAXIMO_PROXIMAS), ordered by date and time.
     *
     * @return Collection<int, Reserva>
     */
    public function proximas(Restaurante $restaurante, string $hoy): Collection
    {
        return Reserva::where('restaurante_id', $restaurante->getKey())
            ->whereDate('fecha', '>=', $hoy)
            ->orderBy('fecha')->orderBy('hora')->orderBy('id')
            ->limit(self::MAXIMO_PROXIMAS)
            ->get();
    }

    /**
     * Figures of today, without the cancelled ones: reservations, expected guests and those still to confirm.
     *
     * @return array{reservas_hoy: int, comensales_esperados: int, por_confirmar: int}
     */
    public function resumenDelDia(Restaurante $restaurante): array
    {
        $deHoy = fn () => Reserva::where('restaurante_id', $restaurante->getKey())
            ->whereDate('fecha', $this->hoy($restaurante))
            ->where('estado', '!=', 'cancelada');

        return [
            'reservas_hoy' => $deHoy()->count(),
            'comensales_esperados' => (int) $deHoy()->sum('personas'),
            'por_confirmar' => $deHoy()->where('estado', 'pendiente')->count(),
        ];
    }

    /**
     * La operación del día para Inicio, en dos consultas: las reservas de hoy que no están canceladas (de ahí salen las
     * cuentas, los comensales y la próxima) y las pendientes de hoy en adelante.
     *
     * La próxima es la primera de hoy, pendiente o confirmada, cuya hora no ha pasado en la zona del restaurante.
     *
     * @return array{reservas_hoy: int, comensales_esperados: int, por_confirmar: int, proxima: ?array{hora: string, personas: int, nombre: ?string}}
     */
    public function operacionDelDia(Restaurante $restaurante): array
    {
        $ahora = now($restaurante->zona_horaria);
        $hoy = $ahora->toDateString();
        $horaActual = $ahora->format('H:i');

        $deHoy = Reserva::where('restaurante_id', $restaurante->getKey())
            ->whereDate('fecha', $hoy)
            ->where('estado', '!=', 'cancelada')
            ->orderBy('hora')->orderBy('id')
            ->get(['id', 'nombre', 'personas', 'hora', 'estado']);

        $proxima = $deHoy->first(
            fn (Reserva $reserva): bool => in_array($reserva->estado, ['pendiente', 'confirmada'], true)
                && substr((string) $reserva->getRawOriginal('hora'), 0, 5) >= $horaActual,
        );

        $porConfirmar = Reserva::where('restaurante_id', $restaurante->getKey())
            ->whereDate('fecha', '>=', $hoy)
            ->where('estado', 'pendiente')
            ->count();

        return [
            'reservas_hoy' => $deHoy->count(),
            'comensales_esperados' => (int) $deHoy->sum('personas'),
            'por_confirmar' => $porConfirmar,
            'proxima' => $proxima === null ? null : [
                'hora' => substr((string) $proxima->getRawOriginal('hora'), 0, 5),
                'personas' => (int) $proxima->personas,
                'nombre' => filled($proxima->nombre) ? (string) $proxima->nombre : null,
            ],
        ];
    }

    /**
     * Pending reservations from today onward (not only today's).
     */
    public function porConfirmarDesdeHoy(Restaurante $restaurante): int
    {
        return Reserva::where('restaurante_id', $restaurante->getKey())
            ->whereDate('fecha', '>=', $this->hoy($restaurante))
            ->where('estado', 'pendiente')
            ->count();
    }
}
