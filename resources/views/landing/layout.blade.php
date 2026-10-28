{{--
    Página principal pública (docs/despliegue.md, "Landing"). Layout propio: sin menuonl.css, sin Tailwind y sin apariencia.js,
    solo el CSS de landing.css (Vite) y un script clásico para el revelado. Sin cookies ni sesión; solo recursos del propio dominio.
    Las dos variantes del logo van como <img> directos (color en la cabecera, negativo en el pie): aquí no hay modo oscuro.
--}}
@php
    $hayBuild = file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot'));
@endphp
<!doctype html>
<html lang="es-MX">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="color-scheme" content="light">
<meta name="theme-color" content="#7CC4F8">
<x-mb.favicons />
@if ($hayBuild)
@vite('resources/css/landing.css')
@endif
<script src="{{ asset('js/landing-reveal.js').'?v='.filemtime(public_path('js/landing-reveal.js')) }}"></script>
<title>@yield('titulo')</title>
@yield('meta')
</head>
<body>
@include('landing.partials.cabecera')

@yield('contenido')

@include('landing.partials.pie')
</body>
</html>
