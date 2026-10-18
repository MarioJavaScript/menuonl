<?php

namespace App\Services;

use NumberFormatter;

class FormatoMoneda
{
    /** País por moneda, para que "es" + MXN use el formato de es-MX (punto decimal). */
    private const REGION = [
        'MXN' => 'MX', 'COP' => 'CO', 'ARS' => 'AR', 'CLP' => 'CL',
        'PEN' => 'PE', 'BRL' => 'BR', 'USD' => 'US', 'EUR' => 'ES',
    ];

    /**
     * Localización BCP 47 con la que se formatean los importes (p. ej. es-MX).
     */
    public static function locale(string $idioma, string $moneda): string
    {
        $idioma = str_replace('_', '-', $idioma);

        if (str_contains($idioma, '-')) {
            return $idioma;
        }

        $region = self::REGION[strtoupper($moneda)] ?? null;

        return $region ? $idioma.'-'.$region : $idioma;
    }

    /**
     * Formatea un importe con el símbolo y los separadores propios de la moneda y el idioma.
     * Si el símbolo es un "$" desnudo (ambiguo entre pesos y dólares) se añade el código.
     */
    public static function formatear(float|string|null $importe, string $moneda, string $idioma = 'es'): string
    {
        $valor = (float) $importe;
        $moneda = strtoupper($moneda);

        if (class_exists(NumberFormatter::class)) {
            $formateado = (new NumberFormatter(self::locale($idioma, $moneda), NumberFormatter::CURRENCY))
                ->formatCurrency($valor, $moneda);

            if ($formateado !== false) {
                return preg_match('/(?<![A-Za-z])\$/', $formateado) ? $formateado.' '.$moneda : $formateado;
            }
        }

        return number_format($valor, 2).' '.$moneda;
    }
}
