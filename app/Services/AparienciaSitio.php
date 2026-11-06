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
     * Plantilla de un restaurante nuevo y de cualquier valor guardado que ya no existe (vacío, desconocido o retirado).
     */
    public const string PLANTILLA_POR_DEFECTO = 'b';

    /**
     * Plantillas cuyo menú usa el estilo visual (Catalogos::temas()). Las demás traen sus propios colores.
     *
     * @var list<string>
     */
    public const array PLANTILLAS_CON_ESTILO = ['b', 'd'];

    /**
     * Plantillas cuyo sitio público muestra la sección Galería. Única fuente de este dato; una prueba la compara
     * con las vistas de resources/views/sitio para que no se desfase sin querer.
     *
     * @var list<string>
     */
    public const array PLANTILLAS_CON_GALERIA = ['b', 'i', 'j', 'k', 'l', 'm', 'n', 'o'];

    /**
     * Contraste mínimo entre el color principal y su texto para que las plantillas e, f, g, h e i lo usen.
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
            'b' => ['nombre' => 'Oscuro elegante', 'descripcion' => 'Fondo negro y fotos con mucho contraste. Se ve sobrio y moderno.', 'para' => 'Restaurantes con buenas fotos de sus platillos', 'fondo' => '#0b0b0b', 'texto' => '#f2f2f2', 'acento' => '#9a9a9a', 'bloque' => '#2c2c2c'],
            'd' => ['nombre' => 'Oscuro con detalles dorados', 'descripcion' => 'Fondo oscuro, detalles dorados y mucho aire. Se siente de autor.', 'para' => 'Cocina de autor, vinos y bares', 'fondo' => '#14120f', 'texto' => '#e8dfcf', 'acento' => '#c9a24a', 'bloque' => '#2a251c'],
            'e' => ['nombre' => 'Cafetería', 'descripcion' => 'Colores tierra y rosa, letras grandes y un menú que se desliza de lado.', 'para' => 'Cafeterías y bebidas', 'fondo' => '#f3e6d8', 'texto' => '#4a2f22', 'acento' => '#c0714f', 'bloque' => '#e8b7b0'],
            'f' => ['nombre' => 'Bar y coctelería', 'descripcion' => 'Fondo oscuro con luz de neón magenta y un menú con líneas punteadas.', 'para' => 'Bares y coctelerías', 'fondo' => '#120b17', 'texto' => '#f3e8ff', 'acento' => '#e11d9c', 'bloque' => '#2b1838'],
            'g' => ['nombre' => 'Cafetería pastel', 'descripcion' => 'Tonos pastel, bordes rasgados y letra cursiva. Dulce y alegre.', 'para' => 'Postres, repostería y cafeterías', 'fondo' => '#fdf0f4', 'texto' => '#7a4a5a', 'acento' => '#f4a8c0', 'bloque' => '#f7d4e0'],
            'h' => ['nombre' => 'Pizzería', 'descripcion' => 'Papel crema, rojo tomate y detalles verde albahaca, con fotos redondas como platos.', 'para' => 'Pizzerías y trattorias', 'fondo' => '#f6efe2', 'texto' => '#2a1a14', 'acento' => '#c4351f', 'bloque' => '#cfdcc4'],
            'i' => ['nombre' => 'Japonés', 'descripcion' => 'Papel claro, tinta negra y rojo japonés, con letras de pincel y fotos redondas.', 'para' => 'Restaurantes japoneses, de sushi y de ramen', 'fondo' => '#f6f5f1', 'texto' => '#151515', 'acento' => '#c8281e', 'bloque' => '#e4e1d8'],
            'j' => ['nombre' => 'Mariscos', 'descripcion' => 'Fondo marino, papel claro y rojo coral, con olas y foto de portada redonda.', 'para' => 'Marisquerías y cocina de mar', 'fondo' => '#0e2a3b', 'texto' => '#f7f4ee', 'acento' => '#c2492c', 'bloque' => '#dce6ea'],
            'k' => ['nombre' => 'Cortes premium', 'descripcion' => 'Papel cálido, tinta oscura, burdeos y dorado, con tipografía clásica. Menú en lista.', 'para' => 'Asadores y cortes premium', 'fondo' => '#1a1614', 'texto' => '#efeae2', 'acento' => '#7a1e1e', 'bloque' => '#c9a46e'],
            'l' => ['nombre' => 'Negro y esmeralda', 'descripcion' => 'Fondo negro con acento verde esmeralda y tipografía de alto contraste. Menú en lista.', 'para' => 'Cocina de autor y cenas', 'fondo' => '#0a0a0a', 'texto' => '#ede8df', 'acento' => '#1f6b4a', 'bloque' => '#7fcfa8'],
            'm' => ['nombre' => 'Hamburguesas', 'descripcion' => 'Fondo casi negro con amarillo mostaza y rojo, y títulos condensados en mayúsculas. Menú en tarjetas.', 'para' => 'Hamburgueserías, papas y malteadas', 'fondo' => '#141414', 'texto' => '#f7f1e3', 'acento' => '#b8322a', 'bloque' => '#e8b020'],
            'n' => ['nombre' => 'Mexicana colorida', 'descripcion' => 'Café oscuro con crema, terracota, verde y dorado, y títulos de letra gruesa con remates. Menú en tarjetas.', 'para' => 'Taquerías, asaderos y cocina mexicana', 'fondo' => '#2a1a12', 'texto' => '#f5e9d7', 'acento' => '#c2441b', 'bloque' => '#f2b33d'],
            'o' => ['nombre' => 'Negro y cobre', 'descripcion' => 'Fondo casi negro con acento cobre y tipografía clásica de alto contraste. Menú en tarjetas.', 'para' => 'Parrillas, mezcalerías y cocina al fuego', 'fondo' => '#0d0c0b', 'texto' => '#f1e9df', 'acento' => '#c77d4a', 'bloque' => '#e0a275'],
        ];
    }

    /**
     * Plantilla con la que se dibuja el restaurante: la guardada si sigue en el catálogo, si no la plantilla por defecto.
     * Única regla de lectura del sitio, del menú y del panel, para que un valor viejo nunca llegue a una vista que no existe.
     */
    public static function plantillaVigente(?string $plantilla): string
    {
        return array_key_exists((string) $plantilla, Catalogos::plantillasSitio()) ? (string) $plantilla : self::PLANTILLA_POR_DEFECTO;
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

    public static function muestraGaleria(?string $plantilla): bool
    {
        return in_array((string) $plantilla, self::PLANTILLAS_CON_GALERIA, true);
    }

    /**
     * Nombres claros (los de Apariencia) de los diseños que muestran la galería, en el orden del catálogo.
     *
     * @return list<string>
     */
    public static function nombresConGaleria(): array
    {
        return array_map(self::nombre(...), self::PLANTILLAS_CON_GALERIA);
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

        if ($plantilla === 'b') {
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
