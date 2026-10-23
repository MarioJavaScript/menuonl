<?php

namespace App\Services;

/**
 * Lo que el dueño necesita saber de cada plantilla del sitio al elegir apariencia: nombre y descripción claros,
 * colores de la imagen de muestra, si el estilo visual le aplica y si su color principal se va a usar.
 *
 * Los nombres de Catalogos::plantillasSitio() no cambian (los usan el panel de servicio y las pruebas); aquí viven
 * los textos pensados para quien administra el restaurante.
 */
class AparienciaSitio
{
    /**
     * Plantillas cuyo menú usa el estilo visual (Catalogos::temas()). Las demás traen sus propios colores.
     *
     * @var list<string>
     */
    public const array PLANTILLAS_CON_ESTILO = ['a', 'b', 'c', 'd'];

    /**
     * Contraste mínimo entre el color principal y su texto para que las plantillas e, f, g y h lo usen.
     */
    private const float CONTRASTE_MINIMO = 4.5;

    /**
     * Fondo del sitio de la plantilla d, sobre el que su color principal debe leerse.
     */
    private const string FONDO_PLANTILLA_D = '#14120f';

    /**
     * @return array<string, array{nombre: string, descripcion: string, para: string, fondo: string, texto: string, acento: string, bloque: string}>
     */
    public static function fichas(): array
    {
        return [
            'a' => ['nombre' => 'Claro y sencillo', 'descripcion' => 'Mucho espacio en blanco y letras grandes. Deja que tus fotos y tu nombre destaquen.', 'para' => 'Casi cualquier restaurante', 'fondo' => '#ffffff', 'texto' => '#1a1a1a', 'acento' => '#8a8a8a', 'bloque' => '#e6e6e6'],
            'b' => ['nombre' => 'Oscuro elegante', 'descripcion' => 'Fondo negro y fotos con mucho contraste. Se ve sobrio y moderno.', 'para' => 'Restaurantes con buenas fotos de sus platillos', 'fondo' => '#0b0b0b', 'texto' => '#f2f2f2', 'acento' => '#9a9a9a', 'bloque' => '#2c2c2c'],
            'c' => ['nombre' => 'Clásico y cálido', 'descripcion' => 'Tonos arena y platillos ordenados por categoría, como una revista.', 'para' => 'Fondas, cocinas tradicionales y familiares', 'fondo' => '#efe3cc', 'texto' => '#3b2f1e', 'acento' => '#b8863b', 'bloque' => '#d9c3a0'],
            'd' => ['nombre' => 'Oscuro con detalles dorados', 'descripcion' => 'Fondo oscuro, detalles dorados y mucho aire. Se siente de autor.', 'para' => 'Cocina de autor, vinos y bares', 'fondo' => '#14120f', 'texto' => '#e8dfcf', 'acento' => '#c9a24a', 'bloque' => '#2a251c'],
            'e' => ['nombre' => 'Cafetería', 'descripcion' => 'Colores tierra y rosa, letras grandes y un menú que se desliza de lado.', 'para' => 'Cafeterías y bebidas', 'fondo' => '#f3e6d8', 'texto' => '#4a2f22', 'acento' => '#c0714f', 'bloque' => '#e8b7b0'],
            'f' => ['nombre' => 'Bar y coctelería', 'descripcion' => 'Fondo oscuro con luz de neón magenta y un menú con líneas punteadas.', 'para' => 'Bares y coctelerías', 'fondo' => '#120b17', 'texto' => '#f3e8ff', 'acento' => '#e11d9c', 'bloque' => '#2b1838'],
            'g' => ['nombre' => 'Cafetería pastel', 'descripcion' => 'Tonos pastel, bordes rasgados y letra cursiva. Dulce y alegre.', 'para' => 'Postres, repostería y cafeterías', 'fondo' => '#fdf0f4', 'texto' => '#7a4a5a', 'acento' => '#f4a8c0', 'bloque' => '#f7d4e0'],
            'h' => ['nombre' => 'Pizzería', 'descripcion' => 'Papel crema, rojo tomate y detalles verde albahaca, con fotos redondas como platos.', 'para' => 'Pizzerías y trattorias', 'fondo' => '#f6efe2', 'texto' => '#2a1a14', 'acento' => '#c4351f', 'bloque' => '#cfdcc4'],
        ];
    }

    /**
     * Nombre de la plantilla pensado para el dueño; si no se conoce, el del catálogo general.
     */
    public static function nombre(string $plantilla): string
    {
        return self::fichas()[$plantilla]['nombre'] ?? (Catalogos::plantillasSitio()[$plantilla]['nombre'] ?? $plantilla);
    }

    public static function usaEstilo(?string $plantilla): bool
    {
        return in_array((string) $plantilla, self::PLANTILLAS_CON_ESTILO, true);
    }

    /**
     * Si el color principal elegido se va a ver en la plantilla, y por qué sí o por qué no.
     *
     * @return array{aplica: bool, mensaje: string}
     */
    public static function estadoDelColor(?string $plantilla, ?string $hex): array
    {
        $plantilla = (string) $plantilla;
        $hex = self::hexValido($hex);

        if ($hex === null) {
            return ['aplica' => false, 'mensaje' => __('panel.apariencia.color.sin_color')];
        }

        if (in_array($plantilla, ['a', 'b', 'c'], true)) {
            return ['aplica' => true, 'mensaje' => __('panel.apariencia.color.solo_menu')];
        }

        $legible = $plantilla === 'd'
            ? self::contraste($hex, self::FONDO_PLANTILLA_D) >= self::CONTRASTE_MINIMO
            : self::contraste($hex, self::textoSobre($hex)) >= self::CONTRASTE_MINIMO;

        return $legible
            ? ['aplica' => true, 'mensaje' => __('panel.apariencia.color.aplica')]
            : ['aplica' => false, 'mensaje' => __('panel.apariencia.color.no_legible')];
    }

    /**
     * Texto que se usa sobre el color principal (el mismo criterio que Restaurante::colorAcentoTexto()).
     */
    private static function textoSobre(string $hex): string
    {
        return self::luminancia($hex) > 0.4 ? '#17171e' : '#ffffff';
    }

    private static function hexValido(?string $hex): ?string
    {
        return preg_match('/\A#[0-9a-fA-F]{6}\z/', (string) $hex) === 1 ? (string) $hex : null;
    }

    private static function luminancia(string $hex): float
    {
        [$r, $g, $b] = array_map(
            fn (string $par): float => hexdec($par) / 255,
            str_split(ltrim($hex, '#'), 2)
        );
        $lineal = fn (float $c): float => $c <= 0.03928 ? $c / 12.92 : (($c + 0.055) / 1.055) ** 2.4;

        return 0.2126 * $lineal($r) + 0.7152 * $lineal($g) + 0.0722 * $lineal($b);
    }

    private static function contraste(string $a, string $b): float
    {
        $la = self::luminancia($a);
        $lb = self::luminancia($b);

        return (max($la, $lb) + 0.05) / (min($la, $lb) + 0.05);
    }
}
