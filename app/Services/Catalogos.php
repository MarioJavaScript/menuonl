<?php

namespace App\Services;

use DateTimeZone;

/**
 * Closed lists used by the panel forms. Validation uses these keys as allow-lists.
 */
class Catalogos
{
    /**
     * @return array<string, string>
     */
    public static function idiomas(): array
    {
        return [
            'es' => 'Español',
            'en' => 'English',
            'pt-BR' => 'Português (Brasil)',
            'fr' => 'Français',
            'de' => 'Deutsch',
            'it' => 'Italiano',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function monedas(): array
    {
        return [
            'MXN' => 'MXN · Peso mexicano',
            'COP' => 'COP · Peso colombiano',
            'ARS' => 'ARS · Peso argentino',
            'CLP' => 'CLP · Peso chileno',
            'PEN' => 'PEN · Sol peruano',
            'BRL' => 'BRL · Real brasileño',
            'USD' => 'USD · Dólar estadounidense',
            'EUR' => 'EUR · Euro',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function zonasHorarias(): array
    {
        $zonas = DateTimeZone::listIdentifiers();

        return array_combine($zonas, $zonas);
    }

    /**
     * @return array<string, string>
     */
    public static function tiposContacto(): array
    {
        return [
            'telefono' => 'Teléfono',
            'whatsapp' => 'WhatsApp',
            'email' => 'Correo',
            'direccion' => 'Dirección',
            'instagram' => 'Instagram',
            'facebook' => 'Facebook',
            'tiktok' => 'TikTok',
            'web' => 'Sitio web',
            'otro' => 'Otro',
        ];
    }

    /**
     * @return array<int, string>
     */
    public static function diasSemana(): array
    {
        return [0 => 'Domingo', 1 => 'Lunes', 2 => 'Martes', 3 => 'Miércoles', 4 => 'Jueves', 5 => 'Viernes', 6 => 'Sábado'];
    }
}
