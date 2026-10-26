@props(['r', 'titulo', 'descripcion' => null, 'pagina' => 'inicio', 'lang' => null, 'js' => false, 'pedido' => false, 'reservas' => false])
@php
    /*
     * Base de la plantilla F (sitio y menú). Solo carga su propia hoja; no usa menuonl.css ni el estilo (tema) del restaurante.
     * El acento del restaurante entra por colorAcentoSeguro() y solo cambia fondos de botones y marcas, con el texto de
     * colorAcentoTexto(), y solo si ese par llega a 4.5:1. Los textos de color usan siempre el magenta de la hoja.
     */
    $luz = function (string $hex): float {
        [$rojo, $verde, $azul] = array_map(fn (string $par): float => hexdec($par) / 255, str_split(ltrim($hex, '#'), 2));
        $lineal = fn (float $c): float => $c <= 0.03928 ? $c / 12.92 : (($c + 0.055) / 1.055) ** 2.4;

        return 0.2126 * $lineal($rojo) + 0.7152 * $lineal($verde) + 0.0722 * $lineal($azul);
    };
    $acento = $r->colorAcentoSeguro();
    $acentoTexto = $r->colorAcentoTexto();
    $usarAcento = $acento !== null && $acentoTexto !== null
        && (max($luz($acento), $luz($acentoTexto)) + 0.05) / (min($luz($acento), $luz($acentoTexto)) + 0.05) >= 4.5;
    $descripcionMeta = \Illuminate\Support\Str::limit(strip_tags((string) ($descripcion ?: $r->eslogan ?: $r->nombre)), 160);
@endphp
<!DOCTYPE html>
<html lang="{{ $lang ?? ($r->idioma_default ?: 'es') }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $titulo }}</title>
    <meta name="description" content="{{ $descripcionMeta }}">
    <meta property="og:type" content="restaurant.restaurant">
    <meta property="og:title" content="{{ $titulo }}">
    <meta property="og:description" content="{{ $descripcionMeta }}">
    @if (filled($r->portada))
        <meta property="og:image" content="{{ app(\App\Services\WebpImageService::class)->url($r->portada) }}">
    @endif
    <x-mb.favicons />
    <link rel="preload" href="/fonts/sitio/bebas-neue-latin-400.woff2" as="font" type="font/woff2" crossorigin>
    @vite(array_values(array_filter(['resources/css/sitio/f.css', $js ? 'resources/js/sitio-bar.js' : null, $pedido ? 'resources/js/sitio-bar-pedido.js' : null, $reservas ? 'resources/js/sitio.js' : null])))
</head>
<body class="sitio sitio-f bar-pagina bar-pagina--{{ $pagina }}" data-plantilla="f"@if ($usarAcento) style="--bar-acento: {{ $acento }}; --bar-sobre-acento: {{ $acentoTexto }};"@endif>
<a class="bar-saltar" href="#principal">Saltar al contenido</a>
{{ $slot }}
</body>
</html>
