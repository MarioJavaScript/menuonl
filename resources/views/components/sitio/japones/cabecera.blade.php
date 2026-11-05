@props(['d', 'actual' => 'inicio', 'mesa' => null, 'estado' => null])
@php
    /*
     * Cabecera de la plantilla I. Con logo, el logo (tamaño máximo fijo en la hoja) junto al nombre; sin logo, solo el nombre.
     * Galería y Horario solo aparecen si su sección existe; Reservar, solo con reservas activas. $estado (true/false) muestra
     * "Abierto" o "Cerrado" en el menú; null no muestra nada.
     */
    $inicioUrl = route('sitio.publico', ['slug' => $d['r']->slug]);
    $ancla = fn (string $id): string => $actual === 'inicio' ? '#'.$id : $inicioUrl.'#'.$id;
    $hayGaleria = ($d['galeria_fotos'] ?? []) !== [];
    $hayVisita = collect($d['horarios'])->contains('cerrado', false) || filled($d['direccion']) || filled($d['mapa_url']);
@endphp
<header class="jap-encabezado">
    <div class="jap-encabezado__interior">
        <div class="jap-encabezado__marca">
            <a class="jap-marca" href="{{ $inicioUrl }}">
                @if (filled($d['r']->logo))<x-sitio.imagen :ruta="$d['r']->logo" alt="" :ancho="88" :alto="88" sizes="44px" :prioridad="true" class="jap-marca__logo" />@endif
                <span>{{ $d['r']->nombre }}</span>
            </a>
            @if ($estado !== null)
                <p @class(['jap-estado', 'jap-estado--abierto' => $estado])>{{ $estado ? 'Abierto' : 'Cerrado' }}</p>
            @endif
            @if (filled($mesa))<span class="jap-mesa">Mesa {{ $mesa }}</span>@endif
        </div>
        <nav class="jap-nav" aria-label="Principal">
            <ul class="jap-nav__lista">
                <li><a class="jap-nav__enlace" href="{{ $d['menu_url'] }}" @if ($actual === 'menu') aria-current="page" @endif>Menú</a></li>
                @if ($hayGaleria)<li><a class="jap-nav__enlace" href="{{ $ancla('galeria') }}">Galería</a></li>@endif
                @if ($hayVisita)<li><a class="jap-nav__enlace" href="{{ $ancla('visitanos') }}">Horario</a></li>@endif
                @if ($d['reservas_activas'] ?? false)<li><a class="jap-nav__enlace jap-nav__reservar" href="{{ $ancla('reservar') }}">Reservar</a></li>@endif
            </ul>
        </nav>
    </div>
</header>
