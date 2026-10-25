@props([
    'titulo',
    'descripcion' => null,
    'lang' => 'es',
    'noindex' => false,
    'sinReferrer' => false,
    'htmlClass' => null,
    'tema' => 'clasico',
    'acento' => null,
    'acentoTexto' => null,
    'js' => 'resources/js/app.js',
    'pedidoJs' => null,
])
<!DOCTYPE html>
<html lang="{{ $lang }}" @class([$htmlClass => filled($htmlClass)])>
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="color-scheme" content="light">
        @if ($noindex)
            <meta name="robots" content="noindex">
        @endif
        @if ($sinReferrer)
            <meta name="referrer" content="no-referrer">
        @endif
        <script src="{{ asset('js/apariencia.js').'?v='.filemtime(public_path('js/apariencia.js')) }}"></script>
        <title>{{ $titulo }}</title>
        @if (filled($descripcion))
            <meta name="description" content="{{ $descripcion }}">
        @endif
        <link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">
        <link rel="stylesheet" href="{{ asset('css/menuonl.css').'?v='.filemtime(public_path('css/menuonl.css')) }}">
        @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
            @vite(array_values(array_filter(['resources/css/app.css', $js, $pedidoJs])))
        @endif
        {{ $head ?? '' }}
    </head>
    <body {{ $attributes->class(['bg-fondo text-texto antialiased'])->merge(['data-tema' => $tema]) }}@if ($acento) style="--mo-acento: {{ $acento }}; --mo-acento-texto: {{ $acentoTexto ?? '#ffffff' }};"@endif>
        {{ $slot }}
    </body>
</html>
