<?php

namespace App\Services;

use Carbon\CarbonImmutable;
use Throwable;

/**
 * Datos del responsable para /privacidad y /terminos. Salen de config('menuonl.legal'); nunca van escritos en las vistas.
 *
 * Un valor vacío, inválido o que siga siendo un marcador ("[POR COMPLETAR]") cuenta como faltante: la página lo omite, de
 * modo que el visitante nunca lo ve, y `menuonl:verificar-produccion` falla hasta que se corrija.
 */
class DatosLegales
{
    /**
     * Clave de config('menuonl.legal') => variable de entorno.
     *
     * @var array<string, string>
     */
    public const array VARIABLES = [
        'responsable' => 'LEGAL_RESPONSABLE',
        'domicilio' => 'LEGAL_DOMICILIO',
        'correo_privacidad' => 'LEGAL_CORREO_PRIVACIDAD',
        'fecha_actualizacion' => 'LEGAL_FECHA_ACTUALIZACION',
        'jurisdiccion' => 'LEGAL_JURISDICCION',
    ];

    private const array MESES = ['enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'];

    public function responsable(): ?string
    {
        return $this->texto('responsable');
    }

    public function domicilio(): ?string
    {
        return $this->texto('domicilio');
    }

    public function jurisdiccion(): ?string
    {
        return $this->texto('jurisdiccion');
    }

    public function correoPrivacidad(): ?string
    {
        $correo = $this->texto('correo_privacidad');

        return $correo !== null && filter_var($correo, FILTER_VALIDATE_EMAIL) !== false ? $correo : null;
    }

    /**
     * La fecha configurada (AAAA-MM-DD) escrita para el lector: "6 de noviembre de 2026". Null si no es una fecha real.
     */
    public function fechaActualizacion(): ?string
    {
        $valor = $this->texto('fecha_actualizacion');

        if ($valor === null) {
            return null;
        }

        try {
            $fecha = CarbonImmutable::createFromFormat('!Y-m-d', $valor);
        } catch (Throwable) {
            return null;
        }

        if ($fecha === null || $fecha->format('Y-m-d') !== $valor) {
            return null;
        }

        return $fecha->day.' de '.self::MESES[$fecha->month - 1].' de '.$fecha->year;
    }

    /**
     * Variables de entorno que faltan o no son válidas. Solo nombres, nunca valores.
     *
     * @return list<string>
     */
    public function faltantes(): array
    {
        $valores = [
            'responsable' => $this->responsable(),
            'domicilio' => $this->domicilio(),
            'correo_privacidad' => $this->correoPrivacidad(),
            'fecha_actualizacion' => $this->fechaActualizacion(),
            'jurisdiccion' => $this->jurisdiccion(),
        ];

        return array_values(array_filter(
            self::VARIABLES,
            fn (string $clave): bool => $valores[$clave] === null,
            ARRAY_FILTER_USE_KEY,
        ));
    }

    /**
     * El valor limpio, o null si está vacío o conserva un marcador de texto pendiente.
     */
    private function texto(string $clave): ?string
    {
        $valor = trim((string) config('menuonl.legal.'.$clave));

        if ($valor === '' || preg_match('/[\[\]]|POR\s+(COMPLETAR|ESCRIBIR|CONFIRMAR|DEFINIR)/iu', $valor) === 1) {
            return null;
        }

        return $valor;
    }
}
