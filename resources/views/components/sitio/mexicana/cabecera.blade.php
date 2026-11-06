@props(['d', 'actual' => 'inicio', 'estado' => null])
@php
    /*
     * Cabecera de la plantilla N. Con logo, el logo (tamaño máximo fijo en la hoja) junto al nombre; sin logo, solo el nombre.
     * Nosotros, Galería y Horario solo aparecen si su sección existe; Reservar (botón con el acento), solo con reservas activas.
     * $estado (true/false) muestra "Abierto" o "Cerrado" en el menú; null no muestra nada.
     */
    $inicioUrl = route('sitio.publico', ['slug' => $d['r']->slug]);
    $ancla = fn (string $id): string => $actual === 'inicio' ? '#'.$id : $inicioUrl.'#'.$id;
    $hayNosotros = filled($d['r']->descripcion);
    $hayGaleria = ($d['galeria_fotos'] ?? []) !== [];
    $hayVisita = collect($d['horarios'])->contains('cerrado', false) || filled($d['direccion']) || filled($d['mapa_url']);
@endphp
<header class="mex-encabezado">
    <div class="mex-encabezado__interior">
        <div class="mex-encabezado__marca">
            <a class="mex-marca" href="{{ $inicioUrl }}">
                @if (filled($d['r']->logo))<x-sitio.imagen :ruta="$d['r']->logo" alt="" :ancho="92" :alto="92" sizes="46px" :prioridad="true" class="mex-marca__logo" />@endif
                <span>{{ $d['r']->nombre }}</span>
            </a>
            @if ($estado !== null)
                <p @class(['mex-estado', 'mex-estado--abierto' => $estado])>{{ $estado ? 'Abierto' : 'Cerrado' }}</p>
            @endif
        </div>
        <nav class="mex-nav" aria-label="Principal">
            <ul class="mex-nav__lista">
                @if ($hayNosotros)<li><a class="mex-nav__enlace" href="{{ $ancla('nosotros') }}">Nosotros</a></li>@endif
                <li><a class="mex-nav__enlace" href="{{ $d['menu_url'] }}" @if ($actual === 'menu') aria-current="page" @endif>Menú</a></li>
                @if ($hayGaleria)<li><a class="mex-nav__enlace" href="{{ $ancla('galeria') }}">Galería</a></li>@endif
                @if ($hayVisita)<li><a class="mex-nav__enlace" href="{{ $ancla('visitanos') }}">Horario</a></li>@endif
                @if ($d['reservas_activas'] ?? false)<li><a class="mex-nav__enlace mex-nav__reservar" href="{{ $ancla('reservar') }}">Reservar</a></li>@endif
            </ul>
        </nav>
    </div>
</header>
