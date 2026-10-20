<?php

namespace App\Services;

use DateTimeZone;

/**
 * Closed lists used by the panel forms. Validation uses these keys as allow-lists.
 */
class Catalogos
{
    public const string PATRON_SLUG = '[a-z0-9]+(?:-[a-z0-9]+)*';

    /**
     * Slugs que un restaurante no puede usar: son el primer segmento de rutas de la aplicación o de archivos
     * públicos, y /{slug} las taparía o se confundiría con ellas. Se prefijan con "livewire" también (ver esSlugReservado).
     *
     * @return list<string>
     */
    public static function slugsReservados(): array
    {
        return [
            'admin', 'panel', 'api', 'q', 'm', 's', 'menu', 'invitaciones', 'login', 'logout', 'register',
            'storage', 'build', 'up', 'livewire', 'filament', 'vendor', 'assets', 'css', 'js', 'fonts', 'images',
            'favicon.ico', 'robots.txt', 'sitemap.xml', 'health', 'diseno', 'sanctum', 'horizon', 'telescope',
            'dashboard', 'password', 'email', 'user', 'users', 'index.php',
        ];
    }

    /**
     * Livewire publica sus rutas bajo un prefijo con hash ("livewire-xxxx"), por eso se compara por prefijo.
     */
    public static function esSlugReservado(string $slug): bool
    {
        return in_array($slug, self::slugsReservados(), true) || str_starts_with($slug, 'livewire');
    }

    /**
     * Expresión del parámetro {slug} en las rutas públicas: formato válido y que no sea un slug reservado.
     */
    public static function patronSlugRuta(): string
    {
        $reservados = implode('|', array_map(fn (string $slug): string => preg_quote($slug, '/'), self::slugsReservados()));

        return '(?!(?:'.$reservados.')(?![a-z0-9-]))(?!livewire)'.self::PATRON_SLUG;
    }

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
     * Plantillas del sitio público (/{slug}): clave => [nombre, descripción].
     *
     * @return array<string, array{nombre: string, descripcion: string}>
     */
    public static function plantillasSitio(): array
    {
        return [
            'a' => ['nombre' => 'Minimal editorial', 'descripcion' => 'Mucho blanco y tipografía grande.'],
            'b' => ['nombre' => 'Oscuro elegante', 'descripcion' => 'Fondo negro y fotos con alto contraste.'],
            'c' => ['nombre' => 'Clásico cálido', 'descripcion' => 'Tonos arena y retícula tipo revista.'],
            'd' => ['nombre' => 'Editorial oscuro', 'descripcion' => 'Fondo oscuro, acento dorado y mucho aire. Para cocina de autor y bares.'],
            'e' => ['nombre' => 'Cafetería', 'descripcion' => 'Colores tierra y rosa, palabras grandes y menú en carrusel. Para cafeterías y bebidas.'],
        ];
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
