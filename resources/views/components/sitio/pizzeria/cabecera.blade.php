@props(['d', 'actual' => 'inicio', 'mesa' => null])
@php
    $inicioUrl = route('sitio.publico', ['slug' => $d['r']->slug]);
@endphp
<header class="piz-encabezado">
    <div class="piz-franja piz-mantel" aria-hidden="true"></div>
    <div class="piz-encabezado__interior">
        <a class="piz-marca" href="{{ $inicioUrl }}">
            @if (filled($d['r']->logo))<x-sitio.imagen :ruta="$d['r']->logo" alt="" :ancho="56" :alto="56" sizes="28px" :prioridad="true" class="piz-marca__logo" />@endif
            <span>{{ $d['r']->nombre }}</span>
        </a>
        @if (filled($mesa))<span class="piz-mesa">Mesa {{ $mesa }}</span>@endif
        <nav class="piz-nav" aria-label="Principal">
            <ul class="piz-nav__lista">
                <li><a class="piz-nav__enlace" href="{{ $inicioUrl }}" @if ($actual === 'inicio') aria-current="page" @endif>Inicio</a></li>
                <li><a class="piz-nav__enlace" href="{{ $d['menu_url'] }}" @if ($actual === 'menu') aria-current="page" @endif>Menú</a></li>
                @if ($d['reservas_activas'] ?? false)<li><a class="piz-nav__enlace piz-nav__reservar" href="{{ $actual === 'inicio' ? '#reservar' : $inicioUrl.'#reservar' }}">Reservar</a></li>@endif
            </ul>
        </nav>
    </div>
</header>
