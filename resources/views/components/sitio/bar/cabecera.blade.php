@props(['d', 'actual' => 'inicio', 'mesa' => null])
@php
    $inicioUrl = route('sitio.publico', ['slug' => $d['r']->slug]);
@endphp
<header class="bar-encabezado">
    <div class="bar-encabezado__interior">
        <a class="bar-marca" href="{{ $inicioUrl }}">
            @if (filled($d['r']->logo))<x-sitio.imagen :ruta="$d['r']->logo" alt="" :ancho="56" :alto="56" sizes="28px" :prioridad="true" class="bar-marca__logo" />@endif
            <span>{{ $d['r']->nombre }}</span>
        </a>
        @if (filled($mesa))<span class="bar-mesa">Mesa {{ $mesa }}</span>@endif
        <nav class="bar-nav" aria-label="Principal">
            <ul class="bar-nav__lista">
                <li><a class="bar-nav__enlace" href="{{ $inicioUrl }}" @if ($actual === 'inicio') aria-current="page" @endif>Inicio</a></li>
                <li><a class="bar-nav__enlace" href="{{ $d['menu_url'] }}" @if ($actual === 'menu') aria-current="page" @endif>Menú</a></li>
                @if ($d['reservas_activas'] ?? false)<li><a class="bar-nav__enlace bar-nav__reservar" href="{{ $actual === 'inicio' ? '#reservar' : $inicioUrl.'#reservar' }}">Reservar</a></li>@endif
            </ul>
        </nav>
    </div>
</header>
