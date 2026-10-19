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
     * Roles of the restaurant team: clave => nombre.
     *
     * @return array<string, string>
     */
    public static function roles(): array
    {
        return [
            'propietario' => 'Propietario',
            'gerente' => 'Gerente',
            'editor' => 'Editor',
            'recepcion' => 'Recepción',
            'cocina' => 'Cocina',
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

    /**
     * Temas de la página pública: clave => [nombre, descripción, acento por defecto].
     *
     * @return array<string, array{nombre: string, descripcion: string, acento: string}>
     */
    public static function temas(): array
    {
        return [
            'calido' => ['nombre' => 'Cálido', 'descripcion' => 'Fondo crema, tipografía con serifa y acento terracota. Elegante y acogedor.', 'acento' => '#a8541c'],
            'editorial' => ['nombre' => 'Editorial oscuro', 'descripcion' => 'Fondo oscuro, detalles dorados y mucho aire. Para cocina de autor o bares.', 'acento' => '#c9a24a'],
            'rustico' => ['nombre' => 'Rústico', 'descripcion' => 'Tonos de papel y madera con acento verde olivo. Cocina tradicional.', 'acento' => '#5b6b3a'],
            'clasico' => ['nombre' => 'Clásico', 'descripcion' => 'Limpio y neutro; cambia solo a modo oscuro según el teléfono.', 'acento' => '#0f172a'],
        ];
    }

    /**
     * @return array<string, string> clave => nombre, para los selectores.
     */
    public static function nombresDeTemas(): array
    {
        return array_map(fn (array $tema): string => $tema['nombre'], self::temas());
    }

    /**
     * Nombres en español natural para las etiquetas dietéticas y de estado.
     *
     * @return array<string, string>
     */
    public static function etiquetas(): array
    {
        return [
            'vegano' => 'Vegano',
            'vegetariano' => 'Vegetariano',
            'picante' => 'Picante',
            'sin_gluten' => 'Sin gluten',
            'sin_lactosa' => 'Sin lactosa',
            'nuevo' => 'Nuevo',
            'recomendado' => 'Recomendado',
        ];
    }

    /**
     * Devuelve el nombre legible en español de una etiqueta dietética.
     */
    public static function nombreEtiqueta(string $codigo): string
    {
        return self::etiquetas()[$codigo] ?? ucfirst(str_replace('_', ' ', $codigo));
    }
}
