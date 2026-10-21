@props(['d', 'actual' => 'inicio', 'mesa' => null])
@php
    $inicioUrl = route('sitio.publico', ['slug' => $d['r']->slug]);
@endphp
<header class="pas-encabezado">
    <div class="pas-encabezado__interior">
        <a class="pas-marca" href="{{ $inicioUrl }}">
            @if (filled($d['r']->logo))<x-sitio.imagen :ruta="$d['r']->logo" alt="" :ancho="56" :alto="56" sizes="28px" :prioridad="true" class="pas-marca__logo" />@endif
            <span>{{ $d['r']->nombre }}</span>
        </a>
        @if (filled($mesa))<span class="pas-mesa">Mesa {{ $mesa }}</span>@endif
        <nav class="pas-nav" aria-label="Principal">
            <ul class="pas-nav__lista">
                <li><a class="pas-nav__enlace" href="{{ $inicioUrl }}" @if ($actual === 'inicio') aria-current="page" @endif>Inicio</a></li>
                <li><a class="pas-nav__enlace" href="{{ $d['menu_url'] }}" @if ($actual === 'menu') aria-current="page" @endif>Menú</a></li>
                @if ($d['reservas_activas'] ?? false)<li><a class="pas-nav__enlace pas-nav__reservar" href="{{ $actual === 'inicio' ? '#reservar' : $inicioUrl.'#reservar' }}">Reservar</a></li>@endif
            </ul>
        </nav>
    </div>
</header>
