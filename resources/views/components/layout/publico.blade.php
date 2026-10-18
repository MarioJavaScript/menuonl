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
])
<!DOCTYPE html>
<html lang="{{ $lang }}" @class([$htmlClass => filled($htmlClass)])>
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="color-scheme" content="light dark">
        @if ($noindex)
            <meta name="robots" content="noindex">
        @endif
        @if ($sinReferrer)
            <meta name="referrer" content="no-referrer">
        @endif
        <script>
            {{-- Apariencia antes de pintar: misma clave que el panel (light, dark o system). --}}
            (function () {
                var clave = 'filament:theme:mode';
                function aplicar(modo) {
                    if (modo === 'light' || modo === 'dark') {
                        document.documentElement.setAttribute('data-apariencia', modo);
                    } else {
                        document.documentElement.removeAttribute('data-apariencia');
                    }
                }
                var guardado = null;
                try { guardado = localStorage.getItem(clave); } catch (e) {}
                aplicar(guardado);
                document.addEventListener('change', function (evento) {
                    var opcion = evento.target;
                    if (!opcion || !opcion.matches || !opcion.matches('[data-apariencia-opcion]')) {
                        return;
                    }
                    aplicar(opcion.value);
                    try { localStorage.setItem(clave, opcion.value); } catch (e) {}
                    document.querySelectorAll('[data-apariencia-opcion]').forEach(function (otra) {
                        otra.checked = otra.value === opcion.value;
                    });
                });
                document.addEventListener('DOMContentLoaded', function () {
                    var actual = guardado === 'light' || guardado === 'dark' ? guardado : 'system';
                    document.querySelectorAll('[data-apariencia-opcion]').forEach(function (opcion) {
                        opcion.checked = opcion.value === actual;
                    });
                });
            })();
        </script>
        <title>{{ $titulo }}</title>
        @if (filled($descripcion))
            <meta name="description" content="{{ $descripcion }}">
        @endif
        <link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">
        <link rel="stylesheet" href="{{ asset('css/menuonl.css').'?v='.filemtime(public_path('css/menuonl.css')) }}">
        @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
            @vite(['resources/css/app.css', $js])
        @endif
        {{ $head ?? '' }}
    </head>
    <body {{ $attributes->class(['bg-fondo text-texto antialiased'])->merge(['data-tema' => $tema]) }}@if ($acento) style="--mo-acento: {{ $acento }}; --mo-acento-texto: {{ $acentoTexto ?? '#ffffff' }};"@endif>
        {{ $slot }}
    </body>
</html>
