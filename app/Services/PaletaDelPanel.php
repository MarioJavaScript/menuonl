<?php

namespace App\Services;

/**
 * Escalas de color del panel (/admin) para Filament: `primary` (azul Menubly) y `gray` (neutros azulados).
 * Se pasan como arreglos explícitos porque Color::hex() solo conserva el matiz y regenera las luminosidades, así que
 * el azul de acción no quedaría en ningún tono. Contrastes en docs/integracion-dashboard.md y en TemaDelAdminTest.
 *
 * El tono 600 del primary es el fondo del botón (texto en el tono 50, 5.53:1) y el color de enlaces y foco en claro.
 * El 400 (azul bebé) es el enlace y el botón en oscuro. El hover del botón en claro (700) lo fija theme.css.
 */
class PaletaDelPanel
{
    /**
     * @var array<int, string>
     */
    public const array PRIMARIO = [
        50 => '#EAF6FF',
        100 => '#D8ECFD',
        200 => '#BFE0FB',
        300 => '#9DD2FA',
        400 => '#7CC4F8',
        500 => '#1F6CBF',
        600 => '#1B62B5',
        700 => '#154E94',
        800 => '#12417A',
        900 => '#12355B',
        950 => '#0B1F36',
    ];

    /**
     * @var array<int, string>
     */
    public const array GRIS = [
        50 => '#F3F9FF',
        100 => '#E8F1FA',
        200 => '#D5E4F2',
        300 => '#B7CCE0',
        400 => '#8AA6C1',
        500 => '#587390',
        600 => '#44607F',
        700 => '#2F4B6B',
        800 => '#1E3A5C',
        900 => '#12304F',
        950 => '#0B1F36',
    ];

    /**
     * @return array{primary: array<int, string>, gray: array<int, string>}
     */
    public static function colores(): array
    {
        return ['primary' => self::PRIMARIO, 'gray' => self::GRIS];
    }
}
