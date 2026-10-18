<?php

namespace App\Services;

use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

/**
 * Fechas y horas en formato es-MX para lo que ve la persona (DISEÑO.md): "18 oct 2026", "10:45 a. m.".
 * Solo da formato; nunca se usa para guardar ni comparar.
 */
class FormatoFecha
{
    private const MESES = ['ene', 'feb', 'mar', 'abr', 'may', 'jun', 'jul', 'ago', 'sep', 'oct', 'nov', 'dic'];

    /**
     * "13:00" o "13:00:00" → "1:00 p. m."; "00:30" → "12:30 a. m.".
     */
    public static function hora(CarbonInterface|string|null $hora): string
    {
        if ($hora === null || $hora === '') {
            return '';
        }

        $momento = $hora instanceof CarbonInterface ? $hora : Carbon::parse($hora);

        return $momento->format('g:i').($momento->hour < 12 ? ' a. m.' : ' p. m.');
    }

    /**
     * Rango de un turno: "1:00 p. m. – 10:00 p. m.".
     */
    public static function rango(CarbonInterface|string|null $abre, CarbonInterface|string|null $cierra): string
    {
        return self::hora($abre).' – '.self::hora($cierra);
    }

    /**
     * "18 oct 2026".
     */
    public static function fecha(CarbonInterface $fecha): string
    {
        return $fecha->day.' '.self::MESES[$fecha->month - 1].' '.$fecha->year;
    }

    /**
     * "18 oct 2026, 10:45 a. m.", en la zona horaria indicada (la del restaurante).
     */
    public static function fechaHora(CarbonInterface $momento, ?string $zonaHoraria = null): string
    {
        $local = $zonaHoraria ? $momento->copy()->setTimezone($zonaHoraria) : $momento;

        return self::fecha($local).', '.self::hora($local);
    }
}
