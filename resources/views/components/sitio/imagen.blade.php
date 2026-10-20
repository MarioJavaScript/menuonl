@props(['ruta' => null, 'alt' => '', 'ancho' => 960, 'alto' => 1200, 'sizes' => '(min-width: 900px) 33vw, 50vw', 'prioridad' => false])
@php
    $servicio = app(\App\Services\WebpImageService::class);
    $srcset = filled($ruta) ? $servicio->srcset($ruta) : null;
@endphp
@if (filled($ruta))
    <img
        src="{{ $servicio->url($ruta, 960) }}"
        @if ($srcset) srcset="{{ $srcset }}" sizes="{{ $sizes }}" @endif
        alt="{{ $alt }}"
        width="{{ $ancho }}" height="{{ $alto }}"
        @if ($prioridad) fetchpriority="high" @else loading="lazy" decoding="async" @endif
        {{ $attributes->merge(['class' => 'foto']) }}
    >
@else
    {{-- Sin foto: bloque neutro con la misma proporción, para que la retícula no cambie. --}}
    <div role="img" aria-label="{{ $alt }} (foto pendiente)" {{ $attributes->merge(['class' => 'foto foto-vacia']) }}></div>
@endif
