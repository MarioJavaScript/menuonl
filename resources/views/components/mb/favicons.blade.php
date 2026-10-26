{{-- Iconos de la pestaña. Con ?v=filemtime para romper la caché al cambiarlos; `svg` en falso cuando Filament ya pone el suyo. --}}
@props(['svg' => true])
@php
    $url = fn (string $ruta): string => asset($ruta).'?v='.filemtime(public_path($ruta));
@endphp
@if ($svg)
<link rel="icon" href="{{ $url('favicon.svg') }}" type="image/svg+xml">
@endif
<link rel="icon" href="{{ $url('favicon.ico') }}" sizes="16x16 32x32">
<link rel="apple-touch-icon" href="{{ $url('brand/apple-touch-icon.png') }}">
