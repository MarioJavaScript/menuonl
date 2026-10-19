@props(['r', 'plantilla', 'descripcion' => null])
@php
    $titulo = $r->nombre.($r->eslogan ? ' · '.$r->eslogan : '');
    $descripcionMeta = \Illuminate\Support\Str::limit(strip_tags((string) ($descripcion ?: $r->eslogan ?: $r->nombre)), 160);
@endphp
<!DOCTYPE html>
<html lang="{{ $r->idioma_default ?: 'es' }}">
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
    <link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">
    <link rel="preload" href="/fonts/sitio/cormorant-garamond-latin-500-normal.woff2" as="font" type="font/woff2" crossorigin>
    <link rel="preload" href="/fonts/sitio/inter-latin-variable.woff2" as="font" type="font/woff2" crossorigin>
    @vite(['resources/css/sitio/'.$plantilla.'.css', 'resources/js/sitio.js'])
</head>
<body class="sitio sitio-{{ $plantilla }}" data-plantilla="{{ $plantilla }}">
{{ $slot }}
</body>
</html>
